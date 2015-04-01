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
 * AstropayAdapter
 * Implementation of GatewayAdapter for processing payments via Astropay
 */
class AstropayAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Astropay';
	const IDENTIFIER = 'astropay';
	const GLOBAL_PREFIX = 'wgAstropayGateway';

	public function getFormClass() {
		return 'Gateway_Form_Handlebars';
	}

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getResponseType() {
		return 'json';
	}

	function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	function defineDataConstraints() {
		$this->dataConstraints = array(
			'x_login'		=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_trans_key'	=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_invoice'		=> array( 'type' => 'alphanumeric',	'length' => 20, ),
			'x_amount'		=> array( 'type' => 'numeric', ),
			'x_currency'	=> array( 'type' => 'alphanumeric',	'length' => 3, ),
			'x_bank'		=> array( 'type' => 'alphanumeric',	'length' => 3, ),
			'x_country'		=> array( 'type' => 'alphanumeric',	'length' => 2, ),
			'x_description'	=> array( 'type' => 'alphanumeric',	'length' => 200, ),
			'x_iduser'		=> array( 'type' => 'alphanumeric',	'length' => 20, ),
			'x_cpf'			=> array( 'type' => 'alphanumeric',	'length' => 30, ),
			'x_name'		=> array( 'type' => 'alphanumeric', ),
			'x_email'		=> array( 'type' => 'alphanumeric', ),
			'x_bdate'		=> array( 'type' => 'date',	'length' => 8, ),
			'x_address'		=> array( 'type' => 'alphanumeric', ),
			'x_zip'			=> array( 'type' => 'alphanumeric',	'length' => 10, ),
			'x_city'		=> array( 'type' => 'alphanumeric', ),
			'x_state'		=> array( 'type' => 'alphanumeric',	'length' => 2, ),
			'country_code'	=> array( 'type' => 'alphanumeric',	'length' => 2, ),
		);
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
		);
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'full_name',
			'donor_id',
		);
	}

	/**
	 * Define var_map
	 */
	function defineVarMap() {
		$this->var_map = array(
			'x_login'		=> 'merchant_id',
			'x_trans_key'	=> 'merchant_password',
			'x_invoice'		=> 'order_id',
			'x_amount'		=> 'amount',
			'x_currency'	=> 'currency_code',
			'x_bank'		=> 'bank_code',
			'x_country'		=> 'country',
			'x_description'	=> 'description',
			'x_iduser'		=> 'donor_id',
			'x_cpf'			=> 'fiscal_number',
			'x_name'		=> 'full_name',
			'x_email'		=> 'email',
			// We've been told bdate is non-mandatory, despite the docs
			'x_bdate'		=> 'birth_date',
			'x_address'		=> 'street',
			'x_zip'			=> 'zip',
			'x_city'		=> 'city',
			'x_state'		=> 'state',
			'x_document'	=> 'gateway_txn_id',
			'country_code'	=> 'country',
		);
	}

	function defineReturnValueMap() {
		$this->return_value_map = array();
	}

	/**
	 * Sets up the $order_id_meta array.
	 * Should contain the following keys/values:
	 * 'alt_locations' => array( $dataset_name, $dataset_key ) //ordered
	 * 'type' => numeric, or alphanumeric
	 * 'length' => $max_charlen
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = array (
			'alt_locations' => array ( '_POST' => 'x_invoice' ),
			'generate' => TRUE,
			'length' => 20
		);
	}

	function setGatewayDefaults() {}

	function defineTransactions() {
		$this->transactions = array( );

		$this->transactions[ 'NewInvoice' ] = array(
			'path' => 'api_curl/streamline/NewInvoice',
			'request' => array(
				'x_login',
				'x_trans_key', // password
				'x_invoice', // order id
				'x_amount',
				'x_currency',
				'x_bank', // payment submethod bank code
				'x_country',
				'x_description',
				'x_iduser',
				'x_cpf',
				'x_name',
				'x_email',
				'x_address',
				'x_zip',
				'x_city',
				'x_state',
				'control',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'x_description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'type' => 'json',
			)
		);

		$this->transactions[ 'GetBanks' ] = array(
			'path' => 'api_curl/apd/get_banks_by_country',
			'request' => array(
				'x_login',
				'x_trans_key',
				'country_code',
				'type',
			),
			'values' => array(
				'x_login' => $this->accountInfo['Create']['Login'],
				'x_trans_key' => $this->accountInfo['Create']['Password'],
				'type' => 'json',
			)
		);
	}

	public function definePaymentMethods() {
		$this->payment_methods = array(
			'cc' => array(),
		);

		$this->payment_submethods = array();

		if ( self::getGlobal( 'Test' ) ) {
			// Test bank labelled 'GNB' on their site
			// Data for testing in Brazil (other countries can use random #s)
			// Cpf: 00003456789
			// Email: testing@astropaycard.com
			// Name: ASTROPAY TESTING
			// Birthdate: 04/03/1984
			$this->payment_submethods['test'] = array(
				'bankcode'	=> 'TE',
				'label'	=> 'GNB',
				'group'	=> 'cc',
				'validation' => array(),
				'keys' => array(),
			);
		}

		// Visa
		$this->payment_submethods['visa'] = array(
			'bankcode'	=> 'VI',
			'label'	=> 'Visa',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// MasterCard
		$this->payment_submethods['mc'] = array(
			'bankcode'	=> 'MC',
			'label'	=> 'MasterCard',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// American Express
		$this->payment_submethods['amex'] = array(
			'bankcode'	=> 'AE',
			'label'	=> 'American Express',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// Visa Debit
		$this->payment_submethods['visa_debit'] = array(
			'paymentproductid'	=> 'VD',
			'label'	=> 'Visa Debit',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// MasterCard debit
		$this->payment_submethods['mc_debit'] = array(
			'bankcode'	=> 'MD',
			'label'	=> 'Mastercard Debit',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// Elo (Brazil-only)
		$this->payment_submethods['elo'] = array(
			'bankcode'	=> 'EL',
			'label'	=> 'Elo',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// Diners Club
		$this->payment_submethods['dc'] = array(
			'bankcode'	=> 'DC',
			'label'	=> 'Diners Club',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// Hipercard
		$this->payment_submethods['hiper'] = array(
			'bankcode'	=> 'HI',
			'label'	=> 'Hipercard',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);

		// Argencard
		$this->payment_submethods['argen'] = array(
			'bankcode'	=> 'AG',
			'label'	=> 'Argencard',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
	}

	function doPayment() {
		return PaymentResult::fromResults(
			$this->do_transaction( 'NewInvoice' ),
			$this->getFinalStatus()
		);
	}

	/**
	 * Overriding @see GatewayAdapter::getTransactionSpecificValue to add a
	 * calculated signature.
	 * @param string $gateway_field_name
	 * @param boolean $token
	 * @return mixed
	 */
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		if ( $gateway_field_name === 'control' ) {
			$message = $this->getData_Staged( 'order_id' ) . 'V'
				. $this->getData_Staged( 'amount' ) . 'I'
				. $this->getData_Staged( 'donor_id' ) . '2'
				. $this->getData_Staged( 'bank_code' ) . '1'
				. $this->getData_Staged( 'fiscal_number' ) . 'H'
				. /* bdate omitted */ 'G'
				. $this->getData_Staged( 'email' ) .'Y'
				. $this->getData_Staged( 'zip' ) . 'A'
				. $this->getData_Staged( 'street' ) . 'P'
				. $this->getData_Staged( 'city' ) . 'S'
				. $this->getData_Staged( 'state' ) . 'P';
			return $this->calculateSignature( $message );
		}
		return parent::getTransactionSpecificValue( $gateway_field_name, $token );
	}

	/*
	 * Seems more sane to do it this way than provide a single input box
	 * and try to parse out fname and lname.
	 */
	protected function stage_full_name() {
		$this->staged_data['full_name'] = $this->unstaged_data['fname'] . ' ' . $this->unstaged_data['lname'];
	}

	/**
	 * They need a 20 char string for a customer ID, so let's generate one from
	 * the donor's email address.
	 */
	protected function stage_donor_id() {
		$hashed = sha1( $this->unstaged_data['email'] );
		$this->staged_data['donor_id'] = substr( $hashed, 0, 20 );
	}

	function getResponseStatus( $response ) {
	}

	function getResponseErrors( $response ) {
	}

	function getResponseData( $response ) {
	}

	static function getCurrencies() {
		$currencies = array(
			'ARS', // Argentinian peso
			'BOB', // Bolivian Boliviano
			'BRL', // Brazilian Real
			'BZD', // Belize Dollar
			'CLP', // Chilean Peso
			'COP', // Colombian Peso
			'MXN', // Mexican Peso
			'PEN', // Peruvian Nuevo Sol
			'USD', // U.S. dollar
		);
		return $currencies;
	}

	function processResponse( $response = null, &$retryVars = null ) {
	}

	protected function calculateSignature( $message ) {
		$key = $this->accountInfo['SecretKey'];
		return strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $message ), pack( 'A*', $key ) )
		);
	}
}
