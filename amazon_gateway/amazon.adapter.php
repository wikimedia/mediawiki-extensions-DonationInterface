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
 * @see https://amazonpayments.s3.amazonaws.com/FPS_ASP_Guides/ASP_Advanced_Users_Guide.pdf
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
		$this->var_map = array(
			"amount" => "amount",
			"transactionAmount" => "amount",
			"transactionId" => "gateway_txn_id",
			"status" => "gateway_status",
			"buyerEmail" => "email",
			"transactionDate" => "date_collect",
			"buyerName" => "fname", // This is dealt with in addDataFromURI()
			"errorMessage" => "error_message",
			"paymentMethod" => "payment_submethod",
			"referenceId" => "contribution_tracking_id",
		);
	}

	function defineAccountInfo() {
		//XXX since this class actually accesses two different endpoints,
		// the usefulness of this function is uncertain.  In other words,
		// account info is transaction-specific.  We use account_config
		// instead
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

		$this->error_map = array(
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
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

	protected function buildRequestParams() {
		$queryparams = parent::buildRequestParams();
		ksort( $queryparams );

		return $queryparams;
	}

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

	protected function authorizeAndCapturePayment() {
		$client = $this->getPwaClient();
		$orderReferenceId = $this->getData_Staged( 'order_reference_id' );

		$authResponse = $client->authorize( array(
			'amazon_order_reference_id' => $orderReferenceId,
			'authorization_amount' => $this->getData_Staged( 'amount' ),
			'currency_code' => $this->getData_Staged( 'currency_code' ),
			'capture_now' => true,
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
		$captureId = $authDetails['IdList']['member']; // IdList generally contains the IDs for the next stages
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
	protected function stage_order_id() {
		$this->staged_data['order_id'] = str_replace( '.', '-', $this->getData_Unstaged_Escaped( 'order_id' ) );
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
	 * Don't need this if we use the SDK!
	 */
	public function processResponse( $response ) {

	}

	function encodeQuery( $params ) {
		ksort( $params );
		$query = array();
		foreach ( $params as $key => $value ) {
			$encoded = str_replace( "%7E", "~", rawurlencode( $value ) );
			$query[] = $key . "=" . $encoded;
		}
		return implode( "&", $query );
	}

	function signRequest( $host, $path, &$params ) {
		unset( $params['signature'] );

		$secret_key = $this->account_config[ "SecretKey" ];

		$query_str = $this->encodeQuery( $params );
		$path_encoded = str_replace( "%2F", "/", rawurlencode( $path ) );

		$message = "GET\n{$host}\n{$path_encoded}\n{$query_str}";

		return rawurlencode( base64_encode( hash_hmac( 'sha256', $message, $secret_key, TRUE ) ) );
	}

	/**
	 * We're never POST'ing, just send a Content-type that won't confuse Amazon.
	 */
	function getCurlBaseHeaders() {
		$headers = array(
			'Content-Type: text/html; charset=utf-8',
		);
		return $headers;
	}

	function getCurlBaseOpts() {
		$opts = parent::getCurlBaseOpts();

        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[CURLOPT_CAINFO] = __DIR__ . "/ca-bundle.crt";
        $opts[CURLOPT_CAPATH] = __DIR__ . "/ca-bundle.crt";

		return $opts;
	}

	/**
	 * For the Amazon adapter this is a huge hack! Because we build the transaction differently.
	 * Amazon expectings things to them in the query string, and back via XML. Go figure.
	 *
	 * In any case; do_transaction() does the heavy lifting. And this does nothing; which is
	 * required because otherwise we throw a bunch of silly XML at Amazon that it just ignores.
	 *
	 * @return string|void Nothing :)
	 */
	protected function buildRequestXML( $rootElement = 'XML', $encoding = 'UTF-8' ) {
		return '';
	}

	/**
	 * Amount is returned as a dollar amount, so override base class division by 100.
	 */
	protected function unstage_amount() {
		$this->unstaged_data['amount'] = $this->getData_Staged( 'amount' );
	}

	/**
	 * MakeGlobalVariablesScript handler
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
