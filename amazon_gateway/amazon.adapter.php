<?php

use PayWithAmazon\Client as PwaClient;
use PayWithAmazon\ClientInterface as PwaClientInterface;

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
 * Uses Login and Pay with Amazon widgets and the associated SDK to charge donors
 * See https://payments.amazon.com/documentation
 * and https://github.com/amzn/login-and-pay-with-amazon-sdk-php
 */
class AmazonAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Amazon';
	const IDENTIFIER = 'amazon';
	const GLOBAL_PREFIX = 'wgAmazonGateway';

	// FIXME: return_value_map should handle non-numeric return values
	protected $capture_status_map = array(
		'Completed' => FinalStatus::COMPLETE,
		'Pending' => FinalStatus::PENDING,
		'Declined' => FinalStatus::FAILED,
	);

	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ($this->getData_Unstaged_Escaped( 'payment_method' ) == null ) {
			$this->addRequestData(
				array( 'payment_method' => 'amazon' )
			);
		}
		$this->session_addDonorData();
	}

	public function getFormClass() {
		if ( strpos( $this->dataObj->getVal_Escaped( 'ffname' ), 'error') === 0 ) {
			// TODO: make a mustache error form
			return parent::getFormClass();
		}
		return 'Gateway_Form_Mustache';
	}

	public function getCommunicationType() {
		return 'xml';
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'order_id',
		);
	}

	function defineVarMap() {
		// TODO: maybe use this for mapping gatway data to API call parameters
		$this->var_map = array();
	}

	function defineAccountInfo() {
		// We use account_config instead
		$this->accountInfo = array();
	}
	function defineReturnValueMap() {}
	function defineDataConstraints() {}
	function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'generate' => TRUE,
			'ct_id' => TRUE,
		);
	}

	function setGatewayDefaults() {}

	public function defineErrorMap() {
		$this->error_map = array();
	}

	function defineTransactions() {
		$this->transactions = array();
	}

	public function definePaymentMethods() {
		$this->payment_methods = array(
			'amazon' => array(
				'profile_provided' => true, // Donor needn't enter name/email
			),
		);

		$this->payment_submethods = array(
			'amazon_cc' => array(),
			'amazon_wallet' => array(),
		);
	}

	/**
	 * Note that the Amazon adapter is somewhat unique in that it uses a third
	 * party SDK to make all processor API calls.  Since we're never calling
	 * do_transaction and friends, we synthesize a PaymentTransactionResponse
	 * to hold any errors returned from the SDK.
	 */
	public function doPayment() {
		$resultData = new PaymentTransactionResponse();
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}

		try {
			$this->confirmOrderReference();
			$this->authorizeAndCapturePayment();
		} catch ( ResponseProcessingException $ex ) {
			$resultData->addError(
				$ex->getErrorCode(),
				$ex->getMessage()
			);
		}

		$this->incrementSequenceNumber();

		return PaymentResult::fromResults(
			$resultData,
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
			'sandbox' => $this->getGlobal( 'TestMode' ),
		) );
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
		$client = $this->getPwaClient();

		$orderReferenceId = $this->getData_Staged( 'order_reference_id' );

		$setDetailsResult = $client->setOrderReferenceDetails( array(
			'amazon_order_reference_id' => $orderReferenceId,
			'amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'seller_note' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			'seller_order_reference_id' => $this->getData_Staged( 'order_id' ),
		) )->toArray();
		self::checkErrors( $setDetailsResult );

		$confirmResult = $client->confirmOrderReference( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) )->toArray();
		self::checkErrors( $confirmResult );

		$getDetailsResult = $client->getOrderReferenceDetails( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) )->toArray();
		self::checkErrors( $getDetailsResult );

		$buyerDetails = $getDetailsResult['GetOrderReferenceDetailsResult']['OrderReferenceDetails']['Buyer'];
		$email = $buyerDetails['Email'];
		$name = $buyerDetails['Name'];
		$nameParts = preg_split( '/\s+/', $name, 2 ); // janky_split_name
		$fname = $nameParts[0];
		$lname = isset( $nameParts[1] ) ? $nameParts[1] : '';
		$this->addRequestData( array(
			'email' => $email,
			'fname' => $fname,
			'lname' => $lname,
		) );
	}

	/**
	 * Once the order reference is finalized, we can authorize a payment against
	 * it and capture the funds.  We combine both steps in a single authorize
	 * call.  If the authorization is successful, we can check on the capture
	 * status and close the order reference.  TODO: determine if capture status
	 * check is really needed.  According to our tech contact, Amazon guarantees
	 * that the capture will eventually succeed if the authorization succeeds.
	 */
	protected function authorizeAndCapturePayment() {
		$client = $this->getPwaClient();
		$orderReferenceId = $this->getData_Staged( 'order_reference_id' );

		$authResponse = $client->authorize( array(
			'amazon_order_reference_id' => $orderReferenceId,
			'authorization_amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'capture_now' => true, // combine authorize and capture steps
			'authorization_reference_id' => $this->getData_Staged( 'order_id' ),
			'transaction_timeout' => 0, // authorize synchronously
			// Could set 'SoftDescriptor' to control what appears on CC statement (16 char max, prepended with AMZ*)
		) )->toArray();
		$this->checkErrors( $authResponse );

		$this->logger->info( 'Authorization response: ' . print_r( $authResponse, true ) );
		$authDetails = $authResponse['AuthorizeResult']['AuthorizationDetails'];
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

		$captureResponse = $client->getCaptureDetails( array(
			'amazon_capture_id' => $captureId,
		) )->toArray();
		$this->checkErrors( $captureResponse );

		$this->logger->info( 'Capture details: ' . print_r( $captureResponse, true ) );
		$captureDetails = $captureResponse['GetCaptureDetailsResult']['CaptureDetails'];
		$captureState = $captureDetails['CaptureStatus']['State'];

		$client->closeOrderReference( array(
			'amazon_order_reference_id' => $orderReferenceId,
		) );

		$this->finalizeInternalStatus( $this->capture_status_map[$captureState] );
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

	static function getCurrencies() {
		// See https://payments.amazon.com/sdui/sdui/about?nodeId=73479#feat_countries
		return array(
			'USD',
		);
	}

	/**
	 * Override default behavior
	 */
	function getAvailableSubmethods() {
		return array();
	}

	/**
	 * SDK takes care of most of the dirty work for us
	 */
	public function processResponse( $response ) {}

	/**
	 * MakeGlobalVariablesScript handler, sends settings to Javascript
	 * @param array $vars
	 */
	public function setClientVariables( &$vars ) {
		$vars['wgAmazonGatewayClientID'] = $this->account_config['ClientID'];
		$vars['wgAmazonGatewaySellerID'] = $this->account_config['SellerID'];
		$vars['wgAmazonGatewaySandbox'] = $this->getGlobal( 'TestMode' ) ? true : false;
		$vars['wgAmazonGatewayReturnURL'] = $this->account_config['ReturnURL'];
		$vars['wgAmazonGatewayWidgetScript'] = $this->account_config['WidgetScriptURL'];
		$vars['wgAmazonGatewayLoginScript'] = $this->getGlobal( 'LoginScript' );
	}
}
