<?php
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

	function defineStagedVars() {}
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
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			$resultData = $this->do_transaction( 'DonateMonthly' );
		} else {
			$resultData = $this->do_transaction( 'Donate' );
		}

		return PaymentResult::fromResults(
			$resultData,
			$this->getFinalStatus()
		);
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
		$test = $this->getGlobal( 'TestMode' ) ? true : false;
		$vars['wgAmazonGatewayClientID'] = $this->account_config['ClientID'];
		$vars['wgAmazonGatewaySellerID'] = $this->account_config['SellerID'];
		$vars['wgAmazonGatewaySandbox'] = $test;
		$vars['wgAmazonGatewayReturnURL'] = $this->getGlobal( 'ReturnURL' );
		$vars['wgAmazonGatewayWidgetScript'] = $test
			? $this->getGlobal( 'WidgetScript' )
			: $this->getGlobal( 'TestingWidgetScript' );
		$vars['wgAmazonGatewayLoginScript'] = $this->getGlobal( 'LoginScript' );
	}
}
