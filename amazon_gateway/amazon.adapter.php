<?php

use PayWithAmazon\PaymentsClient as PwaClient;
use PayWithAmazon\PaymentsClientInterface as PwaClientInterface;

/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * Uses Login and Pay with Amazon widgets and a fork of the associated
 * SDK to charge donors.
 *
 * See https://payments.amazon.com/documentation
 * and https://github.com/ejegg/login-and-pay-with-amazon-sdk-php
 */
class AmazonAdapter extends GatewayAdapter {

	const GATEWAY_NAME = 'Amazon';
	const IDENTIFIER = 'amazon';
	const GLOBAL_PREFIX = 'wgAmazonGateway';

	/**
	 * @var PwaClientInterface
	 */
	protected $client;

	// FIXME: return_value_map should handle non-numeric return values
	protected $capture_status_map = array(
		'Completed' => FinalStatus::COMPLETE,
		'Pending' => FinalStatus::PENDING,
		'Declined' => FinalStatus::FAILED,
	);

	// When an authorization or capture is declined, some reason codes indicate
	// a situation where the donor can retry later or try a different card
	protected $retry_errors = array(
		'InternalServerError',
		'RequestThrottled',
		'ServiceUnavailable',
		'ProcessingFailure',
		'InvalidPaymentMethod',
	);

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ( $this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				array( 'payment_method' => 'amazon' )
			);
		}
		$this->session_addDonorData();
	}

	public function getCommunicationType() {
		return 'xml';
	}

	function defineAccountInfo() {
		// We use account_config instead
		$this->accountInfo = array();
	}

	function defineReturnValueMap() {}

	function defineOrderIDMeta() {
		$this->order_id_meta = array(
			'generate' => TRUE,
			'ct_id' => TRUE,
		);
	}

	public function defineErrorMap() {
		parent::defineErrorMap();

		$self = $this;
		$differentCard = function() use ( $self ) {
			$otherWays = $self->localizeGlobal( 'OtherWaysURL' );
			return WmfFramework::formatMessage(
				'donate_interface-donate-error-try-a-different-card-html',
				$otherWays,
				$self->getGlobal( 'ProblemsEmail' )
			);
		};
		// Donor needs to select a different card.
		$this->error_map['InvalidPaymentMethod'] = $differentCard;
	}

	function defineTransactions() {
		$this->transactions = array();
	}

	function getBasedir() {
		return __DIR__;
	}

	/**
	 * Note that the Amazon adapter is somewhat unique in that it uses a third
	 * party SDK to make all processor API calls.  Since we're never calling
	 * do_transaction and friends, we synthesize a PaymentTransactionResponse
	 * to hold any errors returned from the SDK.
	 */
	public function doPayment() {
		$this->client = $this->getPwaClient();

		$this->transaction_response = new PaymentTransactionResponse();
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}

		try {
			if ( $this->getData_Unstaged_Escaped( 'recurring' ) === '1' ) {
				$this->confirmBillingAgreement();
				$this->authorizeAndCapturePayment( true );
			} else {
				$this->confirmOrderReference();
				$this->authorizeAndCapturePayment( false );
			}
		} catch ( ResponseProcessingException $ex ) {
			$this->handleErrors( $ex, $this->transaction_response );
		}

		$this->incrementSequenceNumber();

		return PaymentResult::fromResults(
			$this->transaction_response,
			$this->getFinalStatus()
		);
	}

	/**
	 * Gets a Pay with Amazon client or facsimile thereof
	 * @return PwaClientInterface
	 */
	protected function getPwaClient() {
		return new PwaClient( array(
			'merchant_id' => $this->account_config['SellerID'],
			'access_key' => $this->account_config['MWSAccessKey'],
			'secret_key' => $this->account_config['MWSSecretKey'],
			'client_id' => $this->account_config['ClientID'],
			'region' => $this->account_config['Region'],
			'sandbox' => $this->getGlobal( 'Test' ),
		) );
	}

	/**
	 * Wraps calls to Amazon SDK client with timing and error handling.
	 * Yes, dynamic calls are slower, but these are all web service calls in
	 * the first place.
	 * @param string $functionName
	 * @param array $parameters
	 */
	protected function callPwaClient( $functionName, $parameters ) {
		$callMe = array( $this->client, $functionName );
		try {
			$this->profiler->getStopwatch( $functionName, true );
			$result = call_user_func( $callMe, $parameters )->toArray();
			$this->profiler->saveCommunicationStats(
				'callPwaClient',
				$functionName,
				'Response: ' . print_r( $result, true )
			);
		} catch( Exception $ex ) {
			$this->logger->error( 'SDK client call failed: ' . $ex->getMessage() );
			$donorMessage = WmfFramework::formatMessage( 'donate_interface-processing-error' );
			$this->transaction_response->setCommunicationStatus( false );
			throw new ResponseProcessingException( $donorMessage, ResponseCodes::NO_RESPONSE );
		}
		$this->transaction_response->setCommunicationStatus( true );
		$this->checkErrors( $result );
		return $result;
	}

	protected function addDonorDetails( $donorDetails ) {
		$email = $donorDetails['Email'];
		$name = $donorDetails['Name'];
		$nameParts = preg_split( '/\s+/', $name, 2 ); // janky_split_name
		$fname = $nameParts[0];
		$lname = isset( $nameParts[1] ) ? $nameParts[1] : '';
		$this->addRequestData( array(
			'email' => $email,
			'fname' => $fname,
			'lname' => $lname,
		) );
		// Stash their info in pending queue and logs to fill in data for
		// audit and IPN messages
		$details = $this->getStompTransaction();
		$this->logger->info( 'Got info for Amazon donation: ' . json_encode( $details ) );
		$this->sendPendingMessage();
	}

	/**
	 * Once the order reference or billing agreement is finalized, we can
	 * authorize a payment against it and capture the funds.  We combine both
	 * steps in a single authorize call.  If the authorization is successful,
	 * we can check on the capture status.  TODO: determine if capture status
	 * check is really needed.  According to our tech contact, Amazon guarantees
	 * that the capture will eventually succeed if the authorization succeeds.
	 */
	protected function authorizeAndCapturePayment( $recurring = false ) {
		if ( $recurring ) {
			$authDetails = $this->authorizeOnBillingAgreement();
		} else {
			$authDetails = $this->authorizeOnOrderReference();
		}

		if ( $authDetails['AuthorizationStatus']['State'] === 'Declined' ) {
			throw new ResponseProcessingException(
				WmfFramework::formatMessage( 'php-response-declined' ), // php- ??
				$authDetails['AuthorizationStatus']['ReasonCode']
			);
		}

		// Our authorize call created both an authorization and a capture object
		// The authorization's ID is in $authDetail['AmazonAuthorizationId']
		// IdList has identifiers for related objects, in this case the capture
		$captureId = $authDetails['IdList']['member'];
		// Use capture ID as gateway_txn_id, since we need that for refunds
		$this->addResponseData( array( 'gateway_txn_id' => $captureId ) );
		// And add it to the ambient transaction_response for doStompTransaction
		$this->transaction_response->setGatewayTransactionId( $captureId );

		$this->logger->info( "Getting details of capture $captureId" );
		$captureResponse = $this->callPwaClient( 'getCaptureDetails', array(
			'amazon_capture_id' => $captureId,
		) );

		$captureDetails = $captureResponse['GetCaptureDetailsResult']['CaptureDetails'];
		$captureState = $captureDetails['CaptureStatus']['State'];
		$this->transaction_response->setTxnMessage( $captureState );

		$this->finalizeInternalStatus( $this->capture_status_map[$captureState] );
		$this->postProcessDonation();
	}

	/**
	 * Amazon's widget has made calls to create an order reference object and
	 * has provided us the ID.  We make one API call to set amount, currency,
	 * and our note and local reference ID.  A second call confirms that the
	 * details are valid and moves it out of draft state.  Once it is out of
	 * draft state, we can retrieve the donor's name and email address with a
	 * third API call.
	 */
	protected function confirmOrderReference() {
		$orderReferenceId = $this->getData_Staged( 'order_reference_id' );

		$this->setOrderReferenceDetailsIfUnset( $orderReferenceId );

		$this->logger->info( "Confirming order $orderReferenceId" );
		$this->callPwaClient( 'confirmOrderReference', array(
			'amazon_order_reference_id' => $orderReferenceId,
		) );

		// TODO: either check the status, or skip this call when we already have
		// donor details
		$this->logger->info( "Getting details of order $orderReferenceId" );
		$getDetailsResult = $this->callPwaClient( 'getOrderReferenceDetails', array(
			'amazon_order_reference_id' => $orderReferenceId,
		) );

		$this->addDonorDetails(
			$getDetailsResult['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Buyer']
		);
	}

	/**
	 * Set the order reference details if they haven't been set yet.  Track
	 * which ones have been set in session.
	 * @param string $orderReferenceId
	 */
	protected function setOrderReferenceDetailsIfUnset( $orderReferenceId ) {
		if ( $this->session_getData( 'order_refs', $orderReferenceId ) ) {
			return;
		}
		$this->logger->info( "Setting details for order $orderReferenceId" );
		$this->callPwaClient( 'setOrderReferenceDetails', array(
			'amazon_order_reference_id' => $orderReferenceId,
			'amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'seller_note' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			'seller_order_id' => $this->getData_Staged( 'order_id' ),
		) );
		$orderRefs = WmfFramework::getSessionValue( 'order_refs' );
		$orderRefs[$orderReferenceId] = true;
		WmfFramework::setSessionValue( 'order_refs', $orderRefs );
	}

	protected function authorizeOnOrderReference() {
		$orderReferenceId = $this->getData_Staged( 'order_reference_id' );

		$this->logger->info( "Authorizing and capturing payment on order $orderReferenceId" );
		$authResponse = $this->callPwaClient( 'authorize', array(
			'amazon_order_reference_id' => $orderReferenceId,
			'authorization_amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'capture_now' => true, // combine authorize and capture steps
			'authorization_reference_id' => $this->getData_Staged( 'order_id' ),
			'transaction_timeout' => 0, // authorize synchronously
			// Could set 'SoftDescriptor' to control what appears on CC statement (16 char max, prepended with AMZ*)
			// Use the seller_authorization_note to simulate an error in the sandbox
			// See https://payments.amazon.com/documentation/lpwa/201749840#201750790
			// 'seller_authorization_note' => '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"TransactionTimedOut"}}',
			// 'seller_authorization_note' => '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"InvalidPaymentMethod"}}',
		) );
		return $authResponse['AuthorizeResult']['AuthorizationDetails'];
	}

	protected function confirmBillingAgreement() {
		$billingAgreementId = $this->getData_Staged( 'subscr_id' );
		$this->setBillingAgreementDetailsIfUnset( $billingAgreementId );

		$this->logger->info( "Confirming billing agreement $billingAgreementId" );
		$this->callPwaClient( 'confirmBillingAgreement', array(
			'amazon_billing_agreement_id' => $billingAgreementId,
		) );

		$this->logger->info( "Getting details of billing agreement $billingAgreementId" );
		$getDetailsResult = $this->callPwaClient( 'getBillingAgreementDetails', array(
			'amazon_billing_agreement_id' => $billingAgreementId,
		) );

		$this->addDonorDetails(
			$getDetailsResult['GetBillingAgreementDetailsResult']['BillingAgreementDetails']['Buyer']
		);
	}

	protected function setBillingAgreementDetailsIfUnset( $billingAgreementId ) {
		if ( $this->session_getData( 'billing_agreements', $billingAgreementId ) ) {
			return;
		}
		$this->logger->info( "Setting details for billing agreement $billingAgreementId" );
		$this->callPwaClient( 'setBillingAgreementDetails', array(
			'amazon_billing_agreement_id' => $billingAgreementId,
			'seller_note' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
			'seller_billing_agreement_id' => $this->getData_Staged( 'order_id' ),
		) );
		$billingAgreements = WmfFramework::getSessionValue( 'billing_agreements' );
		$billingAgreements[$billingAgreementId] = true;
		WmfFramework::setSessionValue( 'billing_agreements', $billingAgreements );
	}

	protected function authorizeOnBillingAgreement() {
		$billingAgreementId = $this->getData_Staged( 'subscr_id' );

		$this->logger->info( "Authorizing and capturing payment on billing agreement $billingAgreementId" );
		$authResponse = $this->callPwaClient( 'authorizeOnBillingAgreement', array(
			'amazon_billing_agreement_id' => $billingAgreementId,
			'authorization_amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'capture_now' => true, // combine authorize and capture steps
			'authorization_reference_id' => $this->getData_Staged( 'order_id' ),
			'seller_order_id' => $this->getData_Staged( 'order_id' ),
			'seller_note' => WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' ),
			'transaction_timeout' => 0, // authorize synchronously
			// 'seller_authorization_note' => '{"SandboxSimulation": {"State":"Declined", "ReasonCode":"InvalidPaymentMethod"}}',
		) );
		return $authResponse['AuthorizeOnBillingAgreementResult']['AuthorizationDetails'];
	}

	/**
	 * Replace decimal point with a dash to comply with Amazon's restrictions on
	 * seller reference ID format.
	 */
	public function generateOrderID( $dataObj = null ) {
		$dotted = parent::generateOrderID( $dataObj );
		return str_replace( '.', '-', $dotted );
	}

	/**
	 * @throws ResponseProcessingException if response contains an error
	 * @param array $response
	 */
	static function checkErrors( $response ) {
		if ( !empty( $response['Error'] ) ) {
			throw new ResponseProcessingException(
				$response['Error']['Message'],
				$response['Error']['Code']
			);
		}
	}

	/**
	 * Override default behavior
	 */
	function getAvailableSubmethods() {
		return array();
	}

	/**
	 * MakeGlobalVariablesScript handler, sends settings to Javascript
	 * @param array $vars
	 */
	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		$vars['wgAmazonGatewayClientID'] = $this->account_config['ClientID'];
		$vars['wgAmazonGatewaySellerID'] = $this->account_config['SellerID'];
		$vars['wgAmazonGatewaySandbox'] = $this->getGlobal( 'Test' ) ? true : false;
		$vars['wgAmazonGatewayReturnURL'] = $this->account_config['ReturnURL'];
		$vars['wgAmazonGatewayWidgetScript'] = $this->account_config['WidgetScriptURL'];
		$vars['wgAmazonGatewayLoginScript'] = $this->getGlobal( 'LoginScript' );
		$vars['wgAmazonGatewayFailPage'] = $this->getGlobal( 'FailPage' );
		$vars['wgAmazonGatewayOtherWaysURL'] = $this->localizeGlobal( 'OtherWaysURL' );
	}

	/**
	 * FIXME: this synthesized 'TransactionResponse' is increasingly silly
	 * Maybe make this adapter more normal by adding an 'SDK' communication type
	 * that just creates an array of $data, then overriding curl_transaction
	 * to use the PwaClient.
	 * @param ResponseProcessingException $exception
	 * @param PaymentTransactionResponse $resultData
	 */
	public function handleErrors( $exception, $resultData ) {
		$errorCode = $exception->getErrorCode();
		$resultData->addError(
			$errorCode, $this->getErrorMapByCodeAndTranslate( $errorCode )
		);
		if ( array_search( $errorCode, $this->retry_errors ) === false ) {
			// Fail on anything we don't recognize as retry-able.  For example:
			// These two may show up if we start doing asynchronous authorization
			// 'AmazonClosed',
			// 'AmazonRejected',
			// For synchronous authorization, timeouts usually indicate that the
			// donor's account is under scrutiny, so letting them choose a different
			// card would likely just time out again
			// 'TransactionTimedOut',
			// These seem potentially fraudy - let's pay attention to them
			$this->logger->error( 'Heinous status returned from Amazon: ' . $errorCode );
			$this->finalizeInternalStatus( FinalStatus::FAILED );
		}
	}

}
