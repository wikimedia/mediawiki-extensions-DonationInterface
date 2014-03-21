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
 * GlobalCollectAdapter
 *
 */
class GlobalCollectAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Global Collect';
	const IDENTIFIER = 'globalcollect';
	public $communication_type = 'xml';
	const GLOBAL_PREFIX = 'wgGlobalCollectGateway';

	/**
	 * Add a key to the transaction INSERT_ORDERWITHPAYMENT.
	 *
	 * $this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][ $key ][] = $value 
	 */
	protected function addKeyToTransaction( $value, $type = 'PAYMENT' ) {

		if ( !in_array( $value, $this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][ $type ] ) ) {
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS'][ $type ][] = $value;
		}
	}

	/**
	 * Define accountInfo
	 */
	public function defineAccountInfo() {
		$this->accountInfo = array(
			'MERCHANTID' => $this->account_config[ 'MerchantID' ],
			//'IPADDRESS' => '', //TODO: Not sure if this should be OUR ip, or the user's ip. Hurm. 
			'VERSION' => "1.0",
		);
	}

	/**
	 * Define dataConstraints
	 */
	public function defineDataConstraints() {
		
		$this->dataConstraints = array(
			
			// General fields

			//'ACCOUNTHOLDER'		=> 'account_holder',		AN50
			'account_holder'		=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'ACCOUNTNAME'			=> 'account_name'			AN35
			'account_name'			=> array( 'type' => 'alphanumeric',		'length' => 35, ),
			
			//'ACCOUNTNUMBER'		=> 'account_number'			AN30
			'account_number'		=> array( 'type' => 'alphanumeric',		'length' => 30, ),
			
			//'ADDRESSLINE1E'		=> 'address_line_1e'		AN35
			'address_line_1e'		=> array( 'type' => 'alphanumeric',		'length' => 35, ),

			//'ADDRESSLINE2'		=> 'address_line_2'			AN35
			'address_line_2'		=> array( 'type' => 'alphanumeric',		'length' => 35, ),

			//'ADDRESSLINE3'		=> 'address_line_3'			AN35
			'address_line_3'		=> array( 'type' => 'alphanumeric',		'length' => 35, ),

			//'ADDRESSLINE4'		=> 'address_line_4'			AN35
			'address_line_4'		=> array( 'type' => 'alphanumeric',		'length' => 35, ),

			//'ATTEMPTID'			=> 'attempt_id'				N5
			'attempt_id'			=> array( 'type' => 'numeric',			'length' => 5, ),
			
			// Did not find this one
			//'AUTHORISATIONID'		=> 'authorization_id'		AN18
			'authorization_id'		=> array( 'type' => 'alphanumeric',		'length' => 18, ),
			
			//'AMOUNT'				=> 'amount'					N12
			'amount'				=> array( 'type' => 'numeric',			'length' => 12, ),
			
			//'BANKACCOUNTNUMBER'	=> 'bank_account_number'	AN50
			'bank_account_number'	=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'BANKAGENZIA'			=> 'bank_agenzia'			AN30
			'bank_agenzia'			=> array( 'type' => 'alphanumeric',		'length' => 30, ),

			//'BANKCHECKDIGIT'		=> 'bank_check_digit'		AN2
			'bank_check_digit'		=> array( 'type' => 'alphanumeric',		'length' => 2, ),
			
			//'BANKCODE'			=> 'bank_code'				N5
			'bank_code'				=> array( 'type' => 'numeric',			'length' => 5, ),
			
			//'BANKFILIALE'			=> 'bank_filiale'			AN30
			'bank_filiale'			=> array( 'type' => 'alphanumeric',		'length' => 30, ),

			//'BANKNAME'			=> 'bank_name'				AN40
			'bank_name'				=> array( 'type' => 'alphanumeric',		'length' => 40, ),
			
			//'BRANCHCODE'			=> 'branch_code'			N5
			'branch_code'			=> array( 'type' => 'numeric',			'length' => 5, ),
			
			//'CITY'				=> 'city'					AN40
			'city'					=> array( 'type' => 'alphanumeric',		'length' => 40, ),
			
			//'COUNTRYCODE'			=> 'country'				AN2
			'country'				=> array( 'type' => 'alphanumeric',		'length' => 2, ),
			
			//'COUNTRYCODEBANK'		=> 'country_code_bank'		AN2
			'country_code_bank'		=> array( 'type' => 'alphanumeric',		'length' => 2, ),
			
			//'COUNTRYDESCRIPTION'	=> 'country_description'	AN50
			'country_description'	=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'CUSTOMERBANKCITY'	=> 'customer_bank_city'		AN50
			'customer_bank_city'	=> array( 'type' => 'alphanumeric',		'length' => 50, ),

			//'CUSTOMERBANKSTREET'	=> 'customer_bank_street'	AN30
			'customer_bank_street'	=> array( 'type' => 'alphanumeric',		'length' => 30, ),

			//'CUSTOMERBANKNUMBER'	=> 'customer_bank_number'	N5
			'customer_bank_number'	=> array( 'type' => 'numeric',			'length' => 5, ),

			//'CUSTOMERBANKZIP'		=> 'customer_bank_zip'		AN10
			'customer_bank_zip'		=> array( 'type' => 'alphanumeric',		'length' => 10, ),

			//'CREDITCARDNUMBER'	=> 'card_num'				N19
			'card_num'				=> array( 'type' => 'numeric',			'length' => 19, ),
			
			//'CURRENCYCODE'		=> 'currency_code'			AN3
			'currency_code'			=> array( 'type' => 'alphanumeric',		'length' => 3, ),
			
			//'CVV'					=> 'cvv'					N4
			'cvv'					=> array( 'type' => 'numeric',			'length' => 4, ),
			
			//'DATECOLLECT'			=> 'date_collect'			D8	YYYYMMDD
			'date_collect'			=> array( 'type' => 'date',				'length' => 8, ),
			
			//'DIRECTDEBITTEXT'		=> 'direct_debit_text'		AN50
			'direct_debit_text'		=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'DOMICILIO'			=> 'domicilio'				AN30
			'domicilio'				=> array( 'type' => 'alphanumeric',		'length' => 30, ),

			//'EFFORTID'			=> 'effort_id'				N5
			'effort_id'				=> array( 'type' => 'numeric',			'length' => 5, ),
			
			//'EMAIL'				=> 'email'					AN70
			'email'					=> array( 'type' => 'alphanumeric',		'length' => 70, ),
			
			//'EXPIRYDATE'			=> 'expiration'				N4	MMYY
			'expiration'			=> array( 'type' => 'numeric',			'length' => 4, ),
			
			//'FIRSTNAME'			=> 'fname'					AN15
			'fname'					=> array( 'type' => 'alphanumeric',		'length' => 15, ),
			
			//'IBAN'				=> 'iban'					AN50
			// IBAN is AN21 on direct debit
			'iban'					=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'IPADDRESS'			=> 'user_ip'				AN32
			'user_ip'				=> array( 'type' => 'alphanumeric',		'length' => 32, ),
			
			//'ISSUERID'			=> 'issuer_id'				N4
			'issuer_id'				=> array( 'type' => 'numeric',			'length' => 4, ),
			
			//'LANGUAGECODE'		=> 'language'				AN2
			'language'				=> array( 'type' => 'alphanumeric',		'length' => 2, ),
			
			//'ORDERID'				=> 'order_id'				N10
			'order_id'				=> array( 'type' => 'numeric',			'length' => 10, ),

			//PAYMENTPRODUCTID
			'payment_product'		=> array( 'type' => 'numeric',			'length' => 5, ),
			
			//'PAYMENTREFERENCE'	=> 'payment_reference'		AN20
			'payment_reference'		=> array( 'type' => 'alphanumeric',		'length' => 20, ),
			
			//'PROVINCIA'			=> 'provincia'				AN30
			'provincia'				=> array( 'type' => 'alphanumeric',		'length' => 30, ),

			//'RETURNURL'			=> 'returnto'				AN512
			'returnto'				=> array( 'type' => 'alphanumeric',		'length' => 512, ),
			
			//'SPECIALID'			=> 'special_id'				AN255
			'special_id'			=> array( 'type' => 'alphanumeric',		'length' => 255, ),
			
			//'STATE'				=> 'state'					AN35
			'state'					=> array( 'type' => 'alphanumeric',		'length' => 35, ),
			
			//'STREET'				=> 'street'					AN50
			'street'				=> array( 'type' => 'alphanumeric',		'length' => 50, ),
			
			//'SURNAME'				=> 'lname'					AN35
			'lname'					=> array( 'type' => 'alphanumeric',		'length' => 35, ),
			
			//'SWIFTCODE'			=> 'swift_code'				AN255
			// This is AN11 for several payment types we are not dealing with yet.
			'swift_code'			=> array( 'type' => 'alphanumeric',		'length' => 255, ),
			
			//'TRANSACTIONTYPE'		=> 'transaction_type'		AN2
			'transaction_type'		=> array( 'type' => 'alphanumeric',		'length' => 2, ),

			//'ZIP'					=> 'zip'					AN10
			'zip'					=> array( 'type' => 'alphanumeric',		'length' => 10, ),
		);
	}

	/**
	 * Define error_map
	 *
	 * @todo
	 * - Add: Error messages
	 */
	public function defineErrorMap() {
		
		$this->error_map = array(
			0		=> 'globalcollect_gateway-response-default',
			300620  => 'donate_interface-processing-error',      // Order ID already used in a previous transaction
			430452	=> 'globalcollect_gateway-response-default', // Not authorised :: This message was generated when trying to attempt a direct debit transaction from Belgium.	
			430900	=> 'globalcollect_gateway-response-default', // NO VALID PROVIDERS FOUND FOR COMBINATION MERCHANTID: NNNN, PAYMENTPRODUCT: NNN, COUNTRYCODE: XX, CURRENCYCODE: XXX
			
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
			
			// Do bank validation messages
			//'dbv-50'	=> 'globalcollect_gateway-response-dbv-50', // Account number format incorrect
			//'dbv-80'	=> 'globalcollect_gateway-response-dbv-80', // Account details missing
			//'dbv-330'	=> 'globalcollect_gateway-response-dbv-330', // Check digit format is incorrect
			//'dbv-340'	=> 'globalcollect_gateway-response-dbv-340', // Branch code not submitted
			
		);
	}
	
	/**
	 * Define var_map
	 *
	 * @todo
	 * - RETURNURL: Find out where the returnto URL is supposed to be coming from.
	 */
	public function defineVarMap() {
		
		$this->var_map = array(
			'ACCOUNTHOLDER'		=> 'account_holder',
			'ACCOUNTNAME'		=> 'account_name',
			'ACCOUNTNUMBER'		=> 'account_number',
			'ADDRESSLINE1E'		=> 'address_line_1e', //dd:CH
			'ADDRESSLINE2'		=> 'address_line_2', //dd:CH
			'ADDRESSLINE3'		=> 'address_line_3', //dd:CH
			'ADDRESSLINE4'		=> 'address_line_4', //dd:CH
			'ATTEMPTID'			=> 'attempt_id',
			'AUTHORISATIONID'	=> 'authorization_id',
			'AMOUNT'			=> 'amount',
			'BANKACCOUNTNUMBER'	=> 'bank_account_number',
			'BANKAGENZIA'		=> 'bank_agenzia', // dd:IT
			'BANKCHECKDIGIT'	=> 'bank_check_digit',
			'BANKCODE'			=> 'bank_code',
			'BANKFILIALE'		=> 'bank_filiale', // dd:IT
			'BANKNAME'			=> 'bank_name',
			'BRANCHCODE'		=> 'branch_code',
			'CITY'				=> 'city',
			'COUNTRYCODE'		=> 'country',
			'COUNTRYCODEBANK'	=> 'country_code_bank',
			'COUNTRYDESCRIPTION'=> 'country_description',
			'CUSTOMERBANKCITY'	=> 'customer_bank_city', // dd
			'CUSTOMERBANKSTREET'=> 'customer_bank_street', // dd
			'CUSTOMERBANKNUMBER'=> 'customer_bank_number', // dd
			'CUSTOMERBANKZIP'	=> 'customer_bank_zip', // dd
			'CREDITCARDNUMBER'	=> 'card_num',
			'CURRENCYCODE'		=> 'currency_code',
			'CVV'				=> 'cvv',
			'DATECOLLECT'		=> 'date_collect',
			'DESCRIPTOR'		=> 'descriptor', // eWallets
			'DIRECTDEBITTEXT'	=> 'direct_debit_text',
			'DOMICILIO'			=> 'domicilio', // dd:ES
			'EFFORTID'			=> 'effort_id',
			'EMAIL'				=> 'email',
			'EXPIRYDATE'		=> 'expiration',
			'FIRSTNAME'			=> 'fname',
			'IBAN'				=> 'iban',
			'IPADDRESS'			=> 'server_ip',
			'IPADDRESSCUSTOMER'	=> 'user_ip',
			'ISSUERID'			=> 'issuer_id',
			'LANGUAGECODE'		=> 'language',
			'MERCHANTREFERENCE' => 'contribution_tracking_id', //new as of Feb 2014. See also the staging function.
			'ORDERID'			=> 'order_id',
			'PAYMENTPRODUCTID' => 'payment_product',
			'PAYMENTREFERENCE'	=> 'payment_reference',
			'PROVINCIA'			=> 'provincia', // dd:ES
			'RETURNURL'			=> 'returnto',
			'SPECIALID'			=> 'special_id',
			'STATE'				=> 'state',
			'STREET'			=> 'street',
			'SURNAME'			=> 'lname',
			'SWIFTCODE'			=> 'swift_code',
			'TRANSACTIONTYPE'	=> 'transaction_type', // dd:GB,NL
			'ZIP'				=> 'zip',
			'FISCALNUMBER'		=> 'fiscal_number', //Boletos
		);
	}
	
	/**
	 * Setting some GC-specific defaults. 
	 * @param array $options These get extracted in the parent.
	 */
	function setGatewayDefaults( $options = array ( ) ) {
		$returnTitle = isset( $options['returnTitle'] ) ? $options['returnTitle'] : Title::newFromText( 'Special:GlobalCollectGatewayResult' );
		$returnTo = isset( $options['returnTo'] ) ? $options['returnTo'] : $returnTitle->getFullURL();

		$defaults = array (
			'returnto' => $returnTo,
			'attempt_id' => '1',
			'effort_id' => '1',
		);

		$this->addData( $defaults );
	}

	/**
	 * Define return_value_map
	 */
	public function defineReturnValueMap() {
		$this->return_value_map = array(
			'OK' => true,
			'NOK' => false,
		);
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 0, 70 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 100, 180 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending-poke', 200 ); //The cardholder was successfully authenticated... but we have to DO_FINISHPAYMENT
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 220, 280 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 300 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 310, 350 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'revised', 400 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending-poke', 525 ); //"The payment was challenged by your Fraud Ruleset and is pending" - we never see this.
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 550 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending-poke', 600 ); //Payments sit here until we SET_PAYMENT
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 625, 650 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 800, 975 ); //these are all post-authorized, but technically pre-settled...
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 1000, 1050 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 1100, 99999 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 100000, 999999 ); // 102020 - ACTION 130 IS NOT ALLOWED FOR MERCHANT NNN, IPADDRESS NNN.NNN.NNN.NNN
		
		
		$this->defineGoToThankYouOn();
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
			'alt_locations' => array ( '_GET' => 'order_id' ),
			'generate' => TRUE, //freaking FINALLY.
		);
	}

	/**
	 * Define goToThankYouOn
	 *
	 * The statuses defined in @see GlobalCollectAdapter::$goToThankYouOn will
	 * allow a completed form to go to the Thank you page.
	 *
	 * Allowed:
	 * - complete
	 * - pending
	 * - pending-poke
	 * - revised
	 *
	 * Denied:
	 * - failed
	 * - Any thing else not defined in $goToThankYouOn
	 *
	 */
	public function defineGoToThankYouOn() {
		
		$this->goToThankYouOn = array(
			'complete',
			'pending',
			'pending-poke',
			'revised',
		);
	}
	
	/**
	 * Define transactions
	 *
	 * Please do not add more transactions to this array.
	 *
	 * @todo
	 * - Does  need IPADDRESS? What about the other transactions. Is this the user's IPA?
	 * - Does DO_BANKVALIDATION need HOSTEDINDICATOR?
	 *
	 * This method should define:
	 * - DO_BANKVALIDATION: used prior to INSERT_ORDERWITHPAYMENT for direct debit
	 * - INSERT_ORDERWITHPAYMENT: used for payments
	 * - TEST_CONNECTION: testing connections - is this still valid?
	 * - GET_ORDERSTATUS
	 */
	public function defineTransactions() {
		$this->transactions = array( );

		$this->transactions['DO_BANKVALIDATION'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'GENERAL' => array(
							'ACCOUNTNAME',
							'ACCOUNTNUMBER',
							'AUTHORISATIONID',
							'BANKCHECKDIGIT',
							'BANKCODE',
							'BANKNAME',
							'BRANCHCODE',
							'COUNTRYCODEBANK',
							'DATECOLLECT', // YYYYMMDD
							'DIRECTDEBITTEXT',
							'IBAN',
							'MERCHANTREFERENCE',
							'TRANSACTIONTYPE',
						),
					)
				)
			),
			'values' => array(
				'ACTION' => 'DO_BANKVALIDATION',
			),
		);

		$this->transactions['INSERT_ORDERWITHPAYMENT'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'ORDER' => array(
							'ORDERID',
							'AMOUNT',
							'CURRENCYCODE',
							'LANGUAGECODE',
							'COUNTRYCODE',
							'MERCHANTREFERENCE',
							'IPADDRESSCUSTOMER',
							'EMAIL',
						),
						'PAYMENT' => array(
							'PAYMENTPRODUCTID',
							'AMOUNT',
							'CURRENCYCODE',
							'LANGUAGECODE',
							'COUNTRYCODE',
							'HOSTEDINDICATOR',
							'RETURNURL',
//							'CVV',
//							'EXPIRYDATE',
//							'CREDITCARDNUMBER',
							'AUTHENTICATIONINDICATOR',
							'FIRSTNAME',
							'SURNAME',
							'STREET',
							'CITY',
							'STATE',
							'ZIP',
							'EMAIL',
						)
					)
				)
			),
			'values' => array(
				'ACTION' => 'INSERT_ORDERWITHPAYMENT',
				'HOSTEDINDICATOR' => '1',
				'AUTHENTICATIONINDICATOR' => 0, //default to no 3DSecure ourselves
			),
		);

		$this->transactions['TEST_CONNECTION'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
							'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array( )
				)
			),
			'values' => array(
				'ACTION' => 'TEST_CONNECTION'
			)
		);

		$this->transactions['GET_ORDERSTATUS'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'ORDER' => array(
							'ORDERID',
						),
					)
				)
			),
			'values' => array(
				'ACTION' => 'GET_ORDERSTATUS',
				'VERSION' => '2.0'
			),
//			'loop_for_status' => array(
//				//'pending',
//				'pending-poke',
//				'complete',
//				'failed',
//				'revised',
//			)
		);
		
		$this->transactions['CANCEL_PAYMENT'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'PAYMENT' => array(
							'ORDERID',
							'EFFORTID',
							'ATTEMPTID',
						),
					)
				)
			),
			'values' => array(
				'ACTION' => 'CANCEL_PAYMENT',
				'VERSION' => '1.0'
			),
		);
		
		$this->transactions['SET_PAYMENT'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'PAYMENT' => array(
							'ORDERID',
							'EFFORTID',
							'PAYMENTPRODUCTID',
						),
					)
				)
			),
			'values' => array(
				'ACTION' => 'SET_PAYMENT',
				'VERSION' => '1.0'
			),
		);
		
		$this->transactions['DO_FINISHPAYMENT'] = array(
			'request' => array(
				'REQUEST' => array(
					'ACTION',
					'META' => array(
						'MERCHANTID',
						'IPADDRESS',
						'VERSION'
					),
					'PARAMS' => array(
						'PAYMENT' => array(
							'ORDERID',
							'EFFORTID',
							'ATTEMPTID',
						),
					)
				)
			),
			'values' => array(
				'ACTION' => 'DO_FINISHPAYMENT',
				'VERSION' => '1.0',
			),
		);
	}
	
	/**
	 * Define payment methods
	 *
	 * The credit card group has a catchall for unspecified payment types.
	 */
	public function definePaymentMethods() {
		
		$this->payment_methods = array();
		
		// Bank Transfers
		$this->payment_methods['bt'] = array(
			'label'	=> 'Bank transfer',
			'validation' => array( 'creditCard' => false, ),
			'short_circuit_at' => 'first_iop',
		);
		
		// Credit Cards
		$this->payment_methods['cc'] = array(
			'label'	=> 'Credit Cards',
		);
		
		// Direct Debit
		$this->payment_methods['dd'] = array(
			'label'	=> 'Direct Debit',
			'validation' => array( 'creditCard' => false, ),
			'short_circuit_at' => 'first_iop',
		);
		
		// eWallets
		$this->payment_methods['ew'] = array(
			'label'	=> 'eWallets',
			'validation' => array( 'address' => false, 'creditCard' => false, ),
			'short_circuit_at' => 'first_iop',
			'additional_success_status' => array( 20 ),
		);
		
		// Bank Transfers
		$this->payment_methods['obt'] = array(
			'label'	=> 'Online bank transfer',
			'validation' => array( 'creditCard' => false, ),
			'short_circuit_at' => 'first_iop',
		);
		
		// Real Time Bank Transfers
		$this->payment_methods['rtbt'] = array(
			'label'	=> 'Real time bank transfer',
			'short_circuit_at' => 'first_iop',
			'additional_success_status' => array( 20 ),
		);

		// Cash payments
		$this->payment_methods['cash'] = array(
			'label' => 'Cash payments',
			'short_circuit_at' => 'first_iop',
			'additional_success_status' => array( 55 ), //PENDING AT CUSTOMER - denotes they need to go to the bank, but we've done all we can.
		);

		// *** Define payment submethods ***
		//TODO: deprecate submethod, everything is a first-class method.

		$this->payment_submethods = array();

		/*
		 * Default => Credit Card
		 *
		 * Every payment_method should have a payment_submethod.
		 * This is just a catch to sure some validation happens. 
		 */
		 
		// None specified - This is a catchall to validate all options for credit cards.
		$this->payment_submethods[''] = array(
			'paymentproductid'	=> 0,
			'label'	=> 'Any',
			'group'	=> 'cc',
			'validation' => array( 'address' => true, 'amount' => true, 'email' => true, 'name' => true, ),
			'keys' => array(),
		);

		/*
		 * Bank transfers
		 */
		 
		// Bank Transfer
		$this->payment_submethods['bt'] = array(
			'paymentproductid'	=> 11,
			'label'	=> 'Bank Transfer',
			'validation' => array(),
			'keys' => array(),
		);

		/*
		 * Credit Card
		 */
		 
		// Visa
		$this->payment_submethods['visa'] = array(
			'paymentproductid'	=> 1,
			'label'	=> 'Visa',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// MasterCard
		$this->payment_submethods['mc'] = array(
			'paymentproductid'	=> 3,
			'label'	=> 'MasterCard',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// American Express
		$this->payment_submethods['amex'] = array(
			'paymentproductid'	=> 2,
			'label'	=> 'American Express',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Maestro
		$this->payment_submethods['maestro'] = array(
			'paymentproductid'	=> 117,
			'label'	=> 'Maestro',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Solo
		$this->payment_submethods['solo'] = array(
			'paymentproductid'	=> 118,
			'label'	=> 'Solo',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Laser
		$this->payment_submethods['laser'] = array(
			'paymentproductid'	=> 124,
			'label'	=> 'Laser',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// JCB
		$this->payment_submethods['jcb'] = array(
			'paymentproductid'	=> 125,
			'label'	=> 'JCB',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Discover
		$this->payment_submethods['discover'] = array(
			'paymentproductid'	=> 128,
			'label'	=> 'Discover',
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// CB
		$this->payment_submethods['cb'] = array(
			'paymentproductid'	=> 130,
			'label'	=> 'CB', // Carte Bancaire OR Carte Bleue
			'group'	=> 'cc',
			'validation' => array(),
			'keys' => array(),
		);


		/*
		 * Direct debit
		 *
		 * See: WebCollect 7.1 Technical guide: Appendix H Country-specific direct debit keys
		 *
		 * - keys: These values, which can be found in $this->var_map, will only be put in the request, if they are populated from the form or staging.
		 */
		 
		// Direct debit: AT
		$this->payment_submethods['dd_at'] = array(
			'paymentproductid'	=> 703,
			'label'	=> 'Direct debit: AT',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', /*'BANKNAME',*/ 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: BE
		$this->payment_submethods['dd_be'] = array(
			'paymentproductid'	=> 706,
			'label'	=> 'Direct debit: BE',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'AUTHORISATIONID', 'BANKCHECKDIGIT', 'BANKCODE', 'BANKNAME', 'DIRECTDEBITTEXT', ),
			//'keys' => array( /*'ACCOUNTNAME',*/ 'ACCOUNTNUMBER', 'AUTHORISATIONID', /*'BANKCHECKDIGIT',*/ 'BANKCODE', /*'BANKNAME',*/ 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: CH
		$this->payment_submethods['dd_ch'] = array(
			'paymentproductid'	=> 707,
			'label'	=> 'Direct debit: CH',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'ADDRESSLINE1E', 'ADDRESSLINE2', 'ADDRESSLINE3', 'ADDRESSLINE4', 'BANKCODE', /*'BANKNAME',*/ /*'CUSTOMERBANKCITY', 'CUSTOMERBANKNUMBER', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP',*/ 'DIRECTDEBITTEXT', 'IBAN', ),
		);
		 
		// Direct debit: DE
		$this->payment_submethods['dd_de'] = array(
			'paymentproductid'	=> 702,
			'label'	=> 'Direct debit: DE',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', /*'BANKNAME',*/ 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: ES
		$this->payment_submethods['dd_es'] = array(
			'paymentproductid'	=> 709,
			'label'	=> 'Direct debit: ES',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', /*'BANKNAME',*/ 'BRANCHCODE', 'BANKCHECKDIGIT', /*'CUSTOMERBANKCITY', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP',*/ 'DIRECTDEBITTEXT', /*'DOMICILIO', 'PROVINCIA',*/ ),
		);
		 
		// Direct debit: FR
		$this->payment_submethods['dd_fr'] = array(
			'paymentproductid'	=> 704,
			'label'	=> 'Direct debit: FR',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', /*'BANKNAME',*/ 'BRANCHCODE', 'BANKCHECKDIGIT', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: GB
		$this->payment_submethods['dd_gb'] = array(
			'paymentproductid'	=> 705,
			'label'	=> 'Direct debit: GB',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'AUTHORISATIONID', 'BANKCODE', /*'BANKNAME',*/ 'DIRECTDEBITTEXT', 'TRANSACTIONTYPE', ),
		);
		 
		// Direct debit: IT
		$this->payment_submethods['dd_it'] = array(
			'paymentproductid'	=> 708,
			'label'	=> 'Direct debit: IT',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', /*'BANKNAME',*/ 'BRANCHCODE', 'BANKAGENZIA', 'BANKCHECKDIGIT', /*'BANKFILIALE',*/ /*'CUSTOMERBANKCITY', 'CUSTOMERBANKNUMBER', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP',*/ 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: NL
		$this->payment_submethods['dd_nl'] = array(
			'paymentproductid'	=> 701,
			'label'	=> 'Direct debit: NL',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', /*'BANKNAME',*/ 'DIRECTDEBITTEXT', 'TRANSACTIONTYPE', ),
		);

		/*
		 * eWallets
		 */
		 
		// eWallets PayPal
		$this->payment_submethods['ew_paypal'] = array(
			'paymentproductid'	=> 840,
			'label'	=> 'eWallets: PayPal',
			'group'	=> 'ew',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// eWallets WebMoney
		$this->payment_submethods['ew_webmoney'] = array(
			'paymentproductid'	=> 841,
			'label'	=> 'eWallets: WebMoney',
			'group'	=> 'ew',
			'validation' => array(),
			'keys' => array(),
		);

		// eWallets Yandex
		$this->payment_submethods['ew_yandex'] = array(
			'paymentproductid'	=> 849,
			'label'	=> 'eWallets: Yandex',
			'group'	=> 'ew',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// eWallets Moneybookers
		$this->payment_submethods['ew_moneybookers'] = array(
			'paymentproductid'	=> 843,
			'label'	=> 'eWallets: Moneybookers',
			'group'	=> 'ew',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// eWallets cashU
		$this->payment_submethods['ew_cashu'] = array(
			'paymentproductid'	=> 845,
			'label'	=> 'eWallets: cashU',
			'group'	=> 'ew',
			'validation' => array(),
			'keys' => array(),
		);

		/*
		 * Online bank transfers
		 */
		 
		// Online Bank Transfer Bpay
		$this->payment_submethods['bpay'] = array(
			'paymentproductid'	=> 500,
			'label'	=> 'Online Bank Transfer: Bpay',
			'group'	=> 'obt',
			'validation' => array(),
			'keys' => array(),
		);

		/*
		 * Real time bank transfers
		 */
		 
		// Nordea (Sweden)
		$this->payment_submethods['rtbt_nordea_sweden'] = array(
			'paymentproductid'	=> 805,
			'label'	=> 'Nordea (Sweden)',
			'group'	=> 'rtbt',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Ideal
		$this->payment_submethods['rtbt_ideal'] = array(
			'paymentproductid'	=> 809,
			'label'	=> 'Ideal',
			'group'	=> 'rtbt',
			'validation' => array(),
			'keys' => array(),
			'issuerids' => array( 
				771	=> 'SNS Regio Bank',
				161	=> 'Van Lanschot Bankiers',
				31	=> 'ABN AMRO',
				761	=> 'ASN Bank',
				21	=> 'Rabobank',
				511	=> 'Triodos Bank',
				721	=> 'ING',
				751	=> 'SNS Bank',
				91	=> 'Friesland Bank',
				801 => 'Knab',
			)
		);
		 
		// eNETS
		$this->payment_submethods['rtbt_enets'] = array(
			'paymentproductid'	=> 810,
			'label'	=> 'eNETS',
			'group'	=> 'rtbt',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// Sofortuberweisung/DIRECTebanking
		$this->payment_submethods['rtbt_sofortuberweisung'] = array(
			'paymentproductid'	=> 836,
			'label'	=> 'Sofortuberweisung/DIRECTebanking',
			'group'	=> 'rtbt',
			'validation' => array(),
			'keys' => array(),
		);
		 
		// eps Online-Überweisung
		$this->payment_submethods['rtbt_eps'] = array(
			'paymentproductid'	=> 856,
			'label'	=> 'eps Online-Überweisung',
			'group'	=> 'rtbt',
			'validation' => array(),
			'keys' => array(),
			'issuerids' => array( 
				824	=> 'Bankhaus Spängler',
				825	=> 'Hypo Tirol Bank',
				822	=> 'NÖ HYPO',
				823	=> 'Voralberger HYPO',
				828	=> 'P.S.K.',
				829	=> 'Easy',
				826	=> 'Erste Bank und Sparkassen',
				827	=> 'BAWAG',
				820	=> 'Raifeissen',
				821	=> 'Volksbanken Gruppe',
				831	=> 'Sparda-Bank',
			)
		);

		// Cash Payments - Boletos

		$this->payment_submethods['cash_boleto'] = array(
			'paymentproductid'	=> 1503,
			'label' => 'Boleto Bancario Brazil',
			'group' => 'cash',
			'keys' => array(),
		);

		PaymentMethod::registerMethods( $this->payment_methods );
		PaymentMethod::registerMethods( $this->payment_submethods );
	}

	/**
	 * Get payment method meta
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @param    string    $payment_method    Payment methods contain payment submethods
	 * @throws MWException
	 */
	public function getPaymentMethodMeta( $payment_method ) {
		
		if ( isset( $this->payment_methods[ $payment_method ] ) ) {
			
			return $this->payment_methods[ $payment_method ];
		}
		else {
			$message = 'The payment method [ ' . $payment_method . ' ] was not found.';
			throw new MWException( $message );
		}
	}

	/**
	 * Get payment submethod meta
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @param    string    $payment_submethod    Payment submethods are mapped to paymentproductid
	 * @param array $options
	 * @throws MWException
	 */
	public function getPaymentSubmethodMeta( $payment_submethod, $options = array() ) {
		
		extract( $options );
		
		$log = isset( $log ) ? (boolean) $log : false ;
		
		if ( isset( $this->payment_submethods[ $payment_submethod ] ) ) {
			
			if ( $log ) {
				$this->log( 'Getting payment submethod: ' . ( string ) $payment_submethod );
			}
			
			// Ensure that the validation index is set.
			if ( !isset( $this->payment_submethods[ $payment_submethod ]['validation'] ) ) {
				$this->payment_submethods[ $payment_submethod ]['validation'] = array();
			}
			
			return $this->payment_submethods[ $payment_submethod ];
		}
		else {
			$message = 'The payment submethod [ ' . $payment_submethod . ' ] was not found.';
			throw new MWException( $message );
		}
	}

	//XXX kill all this validation.
	/**
	 * Get payment submethod form validation options
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @param array $options
	 * @return    array
	 */
	public function getPaymentSubmethodFormValidation( $options = array() ) {
		
		$meta = $this->getPaymentSubmethodMeta( $this->getPaymentSubmethod() );
		
		return $meta['validation'];
	}
	
	/**
	 * Because GC has some processes that involve more than one do_transaction 
	 * chained together, we're catching those special ones in an overload and 
	 * letting the rest behave normally. 
	 */
	public function do_transaction( $transaction ) {
		$this->session_addDonorData();
		switch ( $transaction ){
			case 'Confirm_CreditCard' :
				$this->getStopwatch( 'Confirm_CreditCard', true );
				$result = $this->transactionConfirm_CreditCard();
				$this->saveCommunicationStats( 'Confirm_CreditCard', $transaction );
				return $result;
			case 'Direct_Debit' :
				$this->getStopwatch( 'Direct_Debit', true );
				$result = $this->transactionDirect_Debit();
				$this->saveCommunicationStats( 'Direct_Debit', $transaction );
				return $result;
			default:
				return parent::do_transaction( $transaction );
		}
	}
	
	
	private function transactionConfirm_CreditCard(){
		global $wgRequest; //this is for pulling vars straight from the querystring
		$pull_vars = array(
			'CVVRESULT' => 'cvv_result',
			'AVSRESULT' => 'avs_result',
		);
		$addme = array();
		foreach ( $pull_vars as $theirkey => $ourkey) {
			$tmp = $wgRequest->getVal( $theirkey, null );
			if ( !is_null( $tmp ) ) { 
				$addme[$ourkey] = $tmp;
			}
		}
		
		$is_orphan = false;
		if ( count( $addme ) ){ //nothing unusual here. 
			$this->addData( $addme );
			$logmsg = 'CVV Result: ' . $this->getData_Unstaged_Escaped( 'cvv_result' );
			$logmsg .= ', AVS Result: ' . $this->getData_Unstaged_Escaped( 'avs_result' );
			$this->log( $logmsg );
		} else { //this is an orphan transaction. 
			$is_orphan = true;
			//have to change this code range: All these are usually "pending" and 
			//that would still be true...
			//...aside from the fact that if the user has gotten this far, they left 
			//the part where they could add more data. 
			//By now, "incomplete" definitely means "failed" for 0-70.
			$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 0, 70 );
		}
		
		$cancelflag = false; //this will denote the thing we're trying to do with the donation attempt
		$problemflag = false; //this will get set to true, if we can't continue and need to give up and just log the hell out of it. 
		$problemmessage = ''; //to be used in conjunction with the flag.
		$problemseverity = LOG_ERR; //to be used also in conjunction with the flag, to route the message to the appropriate log. Urf.
		$add_antimessage = false; //this tells us if we should add an antimessage when we are done or not.
		$original_status_code = NULL;
		
		$loopcount = $this->getGlobal('RetryLoopCount');
		$loops = 0;
		
		for ( $loops = 0; $loops < $loopcount && !$cancelflag && !$problemflag; ++$loops ){

			$status_result = $this->do_transaction( 'GET_ORDERSTATUS' );
			if ( $is_orphan ){
				if ( array_key_exists('data', $status_result) ) {
					foreach ( $pull_vars as $theirkey => $ourkey) {
						if ( array_key_exists($theirkey, $status_result['data']) ) {
							$addme[$ourkey] = $status_result['data'][$theirkey];
						}
					}
				}
				$gotCVV = false;
				if ( count( $addme ) ){
					$gotCVV = true;
					$this->addData( $addme );
					$logmsg = 'CVV Result: ' . $this->getData_Unstaged_Escaped( 'cvv_result' );
					$logmsg .= ', AVS Result: ' . $this->getData_Unstaged_Escaped( 'avs_result' );
					$this->log( $logmsg );
					if ( $loops === 0 ){ //only want to do this once - it's not going to change.
						$this->runPreProcessHooks();
					}
					$status_result['action'] = $this->getValidationAction();
				} 
			}

			//we filtered
			if ( array_key_exists( 'action', $status_result ) && $status_result['action'] != 'process' ){
				$cancelflag = true;
				$add_antimessage = true; //don't retry: We've fraud-failed them intentionally.
			} elseif ( array_key_exists( 'status', $status_result ) && $status_result['status'] === false ) {
			//can't communicate or internal error
				$problemflag = true;
				$problemmessage = "Can't communicate or internal error."; // /me shrugs - I think the orphan slayer is hitting this sometimes. Confusing.
			}

			$order_status_results = false;
			if ( !$cancelflag && !$problemflag ) {
	//			$order_status_results = $this->getFinalStatus();
				$txn_data = $this->getTransactionData();
				if (isset($txn_data['STATUSID'])){
					if( is_null( $original_status_code ) ){
						$original_status_code = $txn_data['STATUSID'];
					}
					$order_status_results = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $txn_data['STATUSID'] );
				}
				if ( $loops === 0 && $is_orphan && !is_null( $original_status_code ) ){
					//save stats. 
					if (!isset($this->orphanstats) || !isset( $this->orphanstats[$original_status_code] ) ){
						$this->orphanstats[$original_status_code] = 1;
					} else {
						$this->orphanstats[$original_status_code] += 1;
					}				
				}
				if (!$order_status_results){
					$problemflag = true;
					$problemmessage = "We don't have an order status after doing a GET_ORDERSTATUS.";
				}
				switch ( $order_status_results ){
					case 'failed' : 			
					case 'revised' :  
						$add_antimessage = true;
						$cancelflag = true; //makes sure we don't try to confirm.
						break 2;
					case 'complete' :
						$problemflag = true; //nothing to be done.
						$problemmessage = "GET_ORDERSTATUS reports that the payment is already complete.";
						$problemseverity = LOG_INFO;
						$add_antimessage = true;
						break 2;
					case 'pending-poke' :
						if ( $is_orphan && !$gotCVV ){
							$problemflag = true; 
							$problemmessage = "Unable to retrieve orphan cvv/avs results (Communication problem?).";
						}
						
						//none of this should ever execute for a transaction that doesn't use 3d secure...
						if ( $txn_data['STATUSID'] === '200' && ( $loops < $loopcount-1 ) ){
							$this->log( "Running DO_FINISHPAYMENT ($loops)" );

							$dopayment_result = $this->do_transaction( 'DO_FINISHPAYMENT' );
							$dopayment_data = $dopayment_result['data'];
							//Check the txn status and result code to see if we should bother continuing
							if ( $this->getTransactionStatus() ){
								$this->log( "DO_FINISHPAYMENT ($loops) returned with status ID " . $dopayment_data['STATUSID'] );
								if ( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $dopayment_data['STATUSID'] ) === 'failed' ){
									//ack and die. 
									$problemflag = true; //nothing to be done.
									$problemmessage = "DO_FINISHPAYMENT says the payment failed. Giving up forever.";
									$add_antimessage = true;
									$this->finalizeInternalStatus('failed');
								}
							} else {
								$this->log( "DO_FINISHPAYMENT ($loops) returned NOK", LOG_ERR );
							}
							break;
						}
						
						if ( $txn_data['STATUSID'] !== '200' ) {
							break 2; //no need to loop.
						}
						
					case 'pending' :
						//if it's really pending at this point, we need to... 
						//...leave it alone. If we're orphan slaying, this will stay in the queue. 
						break 2;
				}
			}
		}
		
		//if we got here with no problemflag, 
		//confirm or cancel the payment based on $cancelflag 
		if ( !$problemflag ){
			if ( isset( $status_result['data'] ) && is_array( $status_result['data'] ) ){
				//if they're set, get CVVRESULT && AVSRESULT
				$pull_vars['EFFORTID'] = 'effort_id';
				$pull_vars['ATTEMPTID'] = 'attempt_id';
				$addme = array();
				foreach ( $pull_vars as $theirkey => $ourkey) {
					if ( array_key_exists( $theirkey, $status_result['data'] ) ){
						$addme[$ourkey] = $status_result['data'][$theirkey];
					}
				}

				if ( count( $addme ) ){
					$this->addData( $addme );
				}
			}
			
			if ( !$cancelflag ) {
				$final = $this->do_transaction( 'SET_PAYMENT' );
				if ( isset( $final['status'] ) && $final['status'] === true ) {
					$this->finalizeInternalStatus( 'complete' );
					//get the old status from the first txn, and add in the part where we set the payment. 
					$this->setTransactionResult( "Original Response Status (pre-SET_PAYMENT): " . $original_status_code, 'txn_message' );
					$this->runPostProcessHooks();  //stomp is in here
					$add_antimessage = true;
				} else {
					$this->finalizeInternalStatus( 'failed' );
					$problemflag = true;
					$problemmessage = "SET_PAYMENT couldn't communicate properly!";
				}
			} else {
				if ($order_status_results === false){
					//we didn't do the check, because we're going to fail the thing. 
					/**
					 * No need to send an explicit CANCEL_PAYMENT here, because 
					 * the payment has not been set. 
					 * In fact, GC will error out if we try to do that, and tell 
					 * us there is nothing to cancel.
					 */
					$this->finalizeInternalStatus( 'failed' );
					$add_antimessage = true;
				} else {
					//in case we got wiped out, set the final status to what it was before. 
					$this->finalizeInternalStatus( $order_status_results );
				}
			}
		}
		
		if ( $add_antimessage && !$is_orphan ) {
			//As it happens, we can't remove things from the queue here: It 
			//takes way too dang long. (~5 seconds!)
			//So, instead, I'll add an anti-message and deal with it later. (~.01 seconds) 
			$this->log( 'Adding Antimessage' );
			$this->doLimboStompTransaction( true );
		}
		
		if ( $problemflag || $cancelflag ){
			if ( $cancelflag ){ //cancel wins
				$problemmessage = "Cancelling payment";
				$problemseverity = LOG_INFO;
				$errors = array( '1000001' => $problemmessage );
			} else {
				$errors = array( '1000000' => 'Transaction could not be processed due to an internal error.' );
			}
			
			//we have probably had a communication problem that could mean stranded payments. 
			$this->log( $problemmessage, $problemseverity );
			//hurm. It would be swell if we had a message that told the user we had some kind of internal error. 
			$ret = array(
				'status' => false,
				//DO NOT PREPEND $problemmessage WITH ANYTHING! 
				//orphans.php is looking for specific things in position 0.
				'message' => $problemmessage,
				'errors' => $errors,
				'action' => $this->getValidationAction(),
			);
			return $ret;
		}
		
//		return something better... if we need to!
		return $status_result;
	}

    protected function transactionDirect_Debit()
    {  
		$result = $this->do_transaction('DO_BANKVALIDATION');
		if ($result['status'])
		{
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['HOSTEDINDICATOR'] = 0;
			$result = $this->do_transaction('INSERT_ORDERWITHPAYMENT');
			if (isset($result['status']) && $result['status'])
			{  
				if ($this->getFinalStatus() == 'pending-poke')
				{
					$txn_data = $this->getTransactionData();
					$original_status_code = isset( $txn_data['STATUSID']) ? $txn_data['STATUSID'] : 'NOT SET';

					$result = $this->do_transaction('SET_PAYMENT');
					if (isset($result['status']) && $result['status'] === true)
					{
						$this->finalizeInternalStatus( 'complete' );
						$this->runPostProcessHooks();  //stomp is in here
						$this->doLimboStompTransaction( true );
					} else {
						$this->finalizeInternalStatus( 'failed' );
						//get the old status from the first txn, and add in the part where we set the payment. 
						$this->setTransactionResult( "Original Response Status (pre-SET_PAYMENT): " . $original_status_code, 'txn_message' );
					}
				}
            }
        }
        return $result;
    }
	
	/**
	 * Parse the response to get the status. Not sure if this should return a bool, or something more... telling.
	 *
	 * @param array	$response	The response array
	 * @return bool
	 */
	public function getResponseStatus( $response ) {

		$aok = true;

		foreach ( $response->getElementsByTagName( 'RESULT' ) as $node ) {
			if ( array_key_exists( $node->nodeValue, $this->return_value_map ) && $this->return_value_map[$node->nodeValue] !== true ) {
				$aok = false;
			}
		}

		return $aok;
	}

	/**
	 * Parse the response to get the errors in a format we can log and otherwise deal with.
	 * return a key/value array of codes (if they exist) and messages. 
	 *
	 * If the site has $wgDonationInterfaceDisplayDebug = true, then the real
	 * messages will be sent to the client. Messages will not be translated or
	 * obfuscated. 
	 *
	 * @param array	$response	The response array
	 * @return array
	 */
	public function getResponseErrors( $response ) {
		$errors = array( );
		foreach ( $response->getElementsByTagName( 'ERROR' ) as $node ) {
			$code = '';
			$message = '';
			foreach ( $node->childNodes as $childnode ) {
				if ( $childnode->nodeName === "CODE" ) {
					$code = $childnode->nodeValue;
				}
				if ( $childnode->nodeName === "MESSAGE" ) {
					$message = $childnode->nodeValue;
					//I am hereby done screwing around with GC field constraint violations.
					//They vary between ***and within*** payment types, and their docs are a joke.
					if ( in_string( 'DOES NOT HAVE LENGTH', $message ) ) {
						$this->log( $message, LOG_ERR ); 
					}
				}
			}

			$errors[ $code ] = ( $this->getGlobal( 'DisplayDebug' ) ) ? '*** ' . $message : $this->getErrorMapByCodeAndTranslate( $code );
		}
		return $errors;
	}

	/**
	 * Harvest the data we need back from the gateway. 
	 * return a key/value array
	 *
	 * When we set lookup error code ranges, we use GET_ORDERSTATUS as the key for search
	 * because they are only defined for that transaction type.
	 *
	 * @param DOMDocument	$response	The response object
	 * @return array
	 */
	public function getResponseData( $response ) {
		$data = array( );

		$transaction = $this->getCurrentTransaction();

		switch ( $transaction ) {
			case 'INSERT_ORDERWITHPAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				$data['PAYMENT'] = $this->xmlChildrenToArray( $response, 'PAYMENT' );

				//if we have no order ID yet (or it's somehow wrong), retrieve it and put it in the usual place.
				if ( array_key_exists( 'ORDERID', $data ) && ( $data['ORDERID'] != $this->getData_Unstaged_Escaped( 'order_id' ) ) ) {
					$this->log( "inside " . $data['ORDERID'] );
					$this->normalizeOrderID( $data['ORDERID'] );
					$this->log( print_r( $this->getOrderIDMeta(), true ) );
					$this->addData( array ( 'order_id' => $data['ORDERID'] ) );
					$this->log( print_r( $this->getOrderIDMeta(), true ) );
					$this->session_addDonorData();
				}

				//if we're of a type that sends donors off never to return, we should record that here.
				$payment_info = $this->getPaymentMethodMeta( $this->getPaymentMethod() );
				if ( array_key_exists( 'short_circuit_at', $payment_info ) && $payment_info['short_circuit_at'] === 'first_iop' ){
					if ( array_key_exists( 'additional_success_status', $payment_info ) && is_array( $payment_info['additional_success_status'] ) ){
						foreach ( $payment_info['additional_success_status'] as $status ){
							//mangle the definition of success.
							$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', $status );
						}
					}
					if ( $this->getTransactionStatus() ) {
						$this->finalizeInternalStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] )  );
					}
				}
				break;
			case 'DO_BANKVALIDATION':
				$data = $this->xmlChildrenToArray( $response, 'RESPONSE' );
				unset( $data['META'] );
				$data['errors'] = array();
				$data['CHECKSPERFORMED'] = $this->xmlGetChecks( $response );
				$data['VALIDATIONID'] = $this->xmlChildrenToArray( $response, 'VALIDATIONID' );

				// Final Status will already be set if the transaction was unable to communicate properly.
				if ( $this->getTransactionStatus() ) {
					$this->finalizeInternalStatus( $this->checkDoBankValidation( $data ) );
				}

				break;
			case 'GET_ORDERSTATUS':
				$data = $this->xmlChildrenToArray( $response, 'STATUS' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				break;
			case 'DO_FINISHPAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				break;
		}

		return $data;
	}

	/**
	 * Parse the response object for the checked validations 
	 *
	 * @param DOMDocument	$response	The response object
	 * @return array
	 */
	protected function xmlGetChecks( $response ) {
		$data = array(
			'CHECKS' => array(),
		);

		$checks = $response->getElementsByTagName( 'CHECK' );

		foreach ( $checks as $check ) {
			
			// Get the check code
			$checkCode = $check->getElementsByTagName('CHECKCODE')->item(0)->nodeValue;
			
			// Remove zero paddding
			$checkCode = ltrim( $checkCode, '0');
			
			// Convert it too an integer
			settype( $checkCode, 'integer' );
			
			$data['CHECKS'][ $checkCode ] = $check->getElementsByTagName('CHECKRESULT')->item(0)->nodeValue;
		}
		
		// Sort the error codes
		ksort( $data['CHECKS'] );

		return $data;
	}

	/**
	 * Interpret DO_BANKVALIDATION checks performed.
	 *
	 * This will use the error map.
	 *
	 * PASSED is a successful validation.
	 *
	 * ERROR is a validation failure.
	 *
	 * WARNING: For now, this will be ignored.
	 *
	 * NOTCHECKED does not need to be worried about in the check results. These
	 * are supposed to appear if a validation failed, rendering the other
	 * validations pointless to check.
	 *
	 * @todo
	 * - There is a problem with the manual for DO_BANKVALIDATION. Failure should return NOK. Is this only on development?
	 * - Messages are not being translated by the provider.
	 * - What do we do about WARNING? For now, it is fail?
	 * - Get the validation id
	 *
	 * @param array    $data    The data array
	 *
	 * @throws MWException
	 * @return boolean
	 */
	public function checkDoBankValidation( &$data ) {
		$checks = &$data['CHECKSPERFORMED'];

		$isPass = 0;
		$isError = 0;
		$isWarning = 0;
		$isNotChecked = 0;
		
		$return = 'failed';
		
		if ( !is_array( $checks['CHECKS'] ) ) {
			// Should we trigger an error if no checks are performed?
			// For now, just return failed.
			return $return;
		}

		// We only mark validation as a failure if we have warnings or errors.
		$return = 'complete';
		
		foreach ( $checks['CHECKS'] as $checkCode => $checkResult ) {
			// Prefix error codes with dbv for DO_BANKVALIDATION
			$code = 'dbv-' . $checkCode;
			
			if ( $checkResult == 'ERROR' ) {
				$isError++;

				// Message might need to be put somewhere else.
				$data['errors'][ $code ] = $this->getErrorMap( $code );
			} elseif ( $checkResult == 'NOTCHECKED' ) {
				$isNotChecked++;
			} elseif ( $checkResult == 'PASSED' ) {
				$isPass++;

			} elseif ( $checkResult == 'WARNING' ) {
				$isWarning++;

				// Message might need to be put somewhere else.
				$data['errors'][ $code ] = $this->getErrorMap( $code );
			} else {
				$message = 'Unknown check result: (' . $checkResult . ')';
				
				throw new MWException( $message );
			}
		}
		
		// The return text needs to match something in @see $this->defineGoToThankYouOn()
		if ( $isPass ) {
			$return = 'complete';
		}
		
		if ( $isWarning ) {
			$this->log( 'Got warnings from bank validation: ' . print_r( $data['errors'], TRUE ), LOG_ERR );
			$return = 'complete';
		}
		
		if ( $isError ) {
			$return = 'failed';
		}

		return $return;
	}
	
	/**
	 * Gets all the currency codes appropriate for this gateway
	 * @return array of currency codes
	 */
	static function getCurrencies() {
		// If you update this list, also update the list in the exchange_rates drupal module.
		$currencies = array(
			'AED', // UAE dirham
			'ARS', // Argentinian peso
			'AUD', // Australian dollar
			'BBD', // Barbadian dollar
			'BDT', // Bagladesh taka
			'BGN', // Bulgarian lev
			'BHD', // Bahraini dinar
			'BMD', // Bermudian dollar
			'BND', // Brunei dollar
			'BOB', // Bolivia boliviano
			'BRL', // Brazilian real
// - Removed temporarily for WellsFargo (28/06/13)			'BSD', // Bahamian dollar
			'BZD', // Belize dollar
			'CAD', // Canadian dollar
			'CHF', // Swiss franc
			'CLP', // Chilean deso
			'CNY', // Chinese yuan renminbi
			'COP', // Colombia columb
			'CRC', // Costa Rican colon
			'CZK', // Czech koruna
			'DKK', // Danish krone
			'DOP', // Dominican peso
			'DZD', // Algerian dinar
			'EEK', // Estonian kroon
			'EGP', // Egyptian pound
			'EUR', // Euro
			'GBP', // British pound
			'GTQ', // Guatemala quetzal
			'HKD', // Hong Kong dollar
			'HNL', // Honduras lempira
			'HRK', // Croatian kuna
			'HUF', // Hungarian forint
			'IDR', // Indonesian rupiah
			'ILS', // Israeli shekel
			'INR', // Indian rupee
			'JMD', // Jamaican dollar
			'JOD', // Jordanian dinar
			'JPY', // Japanese yen
			'KES', // Kenyan shilling
			'KRW', // South Korean won
// - Removed temporarily for WellsFargo (28/06/13)			'KYD', // Cayman Islands dollar
			'KZT', // Kazakhstani tenge
			'LBP', // Lebanese pound
			'LKR', // Sri Lankan rupee
			'LTL', // Lithuanian litas
			'LVL', // Latvian lats
			'MAD', // Moroccan dirham
			'MKD', // Macedonia denar
			'MUR', // Mauritius rupee
			'MVR', // Maldives rufiyaa
			'MXN', // Mexican peso
			'MYR', // Malaysian ringgit
			'NIO', // Nicaragua Cordoba
			'NOK', // Norwegian krone
			'NZD', // New Zealand dollar
			'OMR', // Omani rial
			'PAB', // Panamanian balboa
			'PEN', // Peru nuevo sol
			'PHP', // Philippine peso
			'PKR', // Pakistani rupee
			'PLN', // Polish złoty
// - Removed temporarily for WellsFargo (23/05/13)			'PYG', // Paraguayan guaraní
			'QAR', // Qatari rial
			'RON', // Romanian leu
			'RUB', // Russian ruble
			'SAR', // Saudi riyal
			'SEK', // Swedish krona
			'SGD', // Singapore dollar
			'SVC', // Salvadoran colón
			'THB', // Thai baht
			'TJS', // Tajikistani Somoni
			'TND', // Tunisan dinar
			'TRY', // Turkish lira
			'TTD', // Trinidad and Tobago dollar
			'TWD', // New Taiwan dollar
			'UAH', // Ukrainian hryvnia
			'UYU', // Uruguayan peso
			'USD', // U.S. dollar
// - Removed temporarily for WellsFargo (28/06/13)			'UZS', // Uzbekistani som
// - removed temporarily (WorldPay)			'VND', // Vietnamese dong
			'VEF', // Venezuelan bolívar
			'XAF', // Central African CFA franc
			'XCD', // East Caribbean dollar
// - Removed temporarily for WellsFargo (28/06/13)			'XOF', // West African CFA franc
			'ZAR', // South African rand
		);
		return $currencies;
	}

	/**
	 * Process the response
	 *
	 * @param array	$response   The response array
	 * @param       $retryVars  If the transaction suffered a recoverable error, this will be
	 *  an array of all variables that need to be recreated and restaged.
	 *
	 * @return An actionable error code if it happened.
	 */
	public function processResponse( $response, &$retryVars = null ) {
		//set the transaction result message
		$responseStatus = isset( $response['data']['STATUSID'] ) ? $response['data']['STATUSID'] : '';
		$this->setTransactionResult( "Response Status: " . $responseStatus, 'txn_message' ); //TODO: Translate for GC. 
		$this->setTransactionResult( $this->getData_Unstaged_Escaped( 'order_id' ), 'gateway_txn_id' );

		$retErrCode = null;

		// We are also curious to know if there were any recoverable errors
		foreach ( $response['errors'] as $errCode => $errMsg ) {
			if ( $retryVars === null ) {
				$retryVars = array();
			}

			switch ( $errCode ) {
				case 300620:
				// Oh no! We've already used this order # somewhere else! Restart!
					$this->log( 'Order ID collission! Starting again.', LOG_ERR );
					$retryVars[] = 'order_id';
					$retErrCode = $errCode;
					break;
				case 400120:
					/* INSERTATTEMPT PAYMENT FOR ORDER ALREADY FINAL FOR COMBINATION.
					* TODO: They already gave us money or failed...
					* but have probably been forbidden from resultswitcher due to session weirdness.
					* What should we actually do with these people, other than the default error log?
					* Special error page saying that they may already have donated?
					* Please note: I have no idea if this happens IRL.
					* I did wrench my test instance into doing this a couple times, though.
					*/
				default:
					$this->log( __FUNCTION__ . " Error $errCode : $errMsg", LOG_ERR );
					break;
			}
		}

		return $retErrCode;
	}

	/**
	 * The default section of the switch will be hit on first time forms. This
	 * should be okay, because we are only concerned with staged_vars that have
	 * been posted.
	 *
	 * Credit cards staged_vars are set to ensure form failures on validation in
	 * the default case. This should prevent accidental form submission with
	 * unknown transaction types. 
	 */
	public function defineStagedVars() {
		//OUR field names. 
		$this->staged_vars = array(
			'amount',
			//'card_num',
			'returnto',
			'payment_method',
			'payment_submethod',
			'payment_product',
			'issuer_id',
			'order_id', //This may or may not oughta-be-here...
			'contribution_tracking_id',
			'language',
			'recurring',
			'country',
			//Street address and zip need to be staged, to provide dummy data in
			//the event that they are sent blank, which will short-circuit all
			//AVS checking for accounts that have AVS data tied to them.
			'street',
			'zip',
			'fiscal_number',
			'branch_code', //Direct Debit
			'account_number', //Direct Debit
			'bank_code', //Direct Debit
		);
	}
	
	protected function stage_language( $type = 'request' ) {
		$language = strtolower( $this->staged_data['language'] );
		
		switch ( $type ) {
			case 'request':
				if ( !in_array( $language, $this->getAvailableLanguages() ) ) {
					$fallbacks = Language::getFallbacksFor( $language );
					foreach ( $fallbacks as $fallback ) {
						if ( in_array( $fallback, $this->getAvailableLanguages() ) ) {
							$language = $fallback;
							break;
						}
					}
				}

				if ( !in_array( $language, $this->getAvailableLanguages() ) ){
					$language = 'en';
				}

				if ( $language === 'zh' ) { //Handles GC's mutant Chinese code.
					$language = 'sc';
				}

				break;
			case 'response':
				if ( $language === 'sc' ){
					$language = 'zh';
				}
				break;
		}
		
		$this->staged_data['language'] = $language;
			
	}
	
	/**
	 * OUR language codes which are available to use in GlobalCollect. 
	 * @return string 
	 */
	function getAvailableLanguages(){
		$languages = array(
			'ar', //Arabic
			'cs', //Czech
			'da', //Danish
			'nl', //Dutch
			'en', //English
			'fa', //Farsi
			'fi', //Finish
			'fr', //French
			'de', //German
			'he', //Hebrew
			'hi', //Hindi
			'hu', //Hungarian
			'it', //Italian
			'ja', //Japanese
			'ko', //Korean
			'no', //Norwegian
			'pl', //Polish
			'pt', //Portuguese
			'ro', //Romanian
			'ru', //Russian
			'sl', //Slovene
			'es', //Spanish
			'sw', //Swahili
			'sv', //Swedish
			'th', //Thai
			'tr', //Turkish
			'ur', //Urdu
			'vi', //Vietnamese
			'zh', //the REAL chinese code. 
		);
		return $languages;
	}
	
	/**
	 * Stage: amount
	 *
	 * For example: JPY 1000.05 get changed to 100005. This need to be 100000.
	 * For example: JPY 1000.95 get changed to 100095. This need to be 100000.
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_amount( $type = 'request' ) {
		switch ( $type ) {
			case 'request':
				
				if ( !DataValidator::is_fractional_currency( $this->staged_data['currency_code'] ) ) {
					$this->staged_data['amount'] = floor( $this->staged_data['amount'] );
				}
				
				$this->staged_data['amount'] = $this->staged_data['amount'] * 100;

				break;
			case 'response':
				$this->staged_data['amount'] = $this->staged_data['amount'] / 100;
				break;
		}
	}

	/**
	 * Stage: card_num
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_card_num( $type = 'request' ) {
		//I realize that the $type isn't used. Voodoo.
		if ( array_key_exists( 'card_num', $this->staged_data ) ) {
			$this->staged_data['card_num'] = str_replace( ' ', '', $this->staged_data['card_num'] );
		}
	}

	/**
	 * Stage: payment_product
	 * Stages the payment product ID for GC.
	 * Not what I had in mind to begin with, but this *completely* blew up.
	 */
	public function stage_payment_product( $type = 'request' ) {
		//cc used to look in card_type, but that's been an alias for payment_submethod for a while. DonationData takes care of it.
		$payment_method = array_key_exists( 'payment_method', $this->staged_data ) ? $this->staged_data['payment_method'] : false;
		$payment_submethod = array_key_exists( 'payment_submethod', $this->staged_data ) ? $this->staged_data['payment_submethod'] : false;

		if ( $payment_method === 'cc' ) {
			//basically do what used to be stage_card_type.
			$types = array (
				'visa' => '1',
				'amex' => '2',
				'mc' => '3',
				'maestro' => '117',
				'solo' => '118',
				'laser' => '124',
				'jcb' => '125',
				'discover' => '128',
				'cb' => '130',
			);

			if ( $type === 'response' ) {
				$types = array_flip( $types );
			}

			if ( (!is_null( $payment_submethod ) ) && array_key_exists( $payment_submethod, $types ) ) {
				$this->staged_data['payment_product'] = $types[$payment_submethod];
			} else {
				if ( !empty( $payment_submethod ) ) {
					$this->log( "Could not find a cc payment product for '$payment_submethod'", LOG_ERR );
				}
			}

			// This array contains all the card types that can use AUTHENTICATIONINDICATOR
			$authenticationIndicatorTypes = array (
				'1', // visa
				'3', // mc
			);

			$enable3ds = false;
			$currency = $this->getData_Unstaged_Escaped( 'currency_code' );
			$country = strtoupper( $this->getData_Unstaged_Escaped( 'country' ) );
			if ( in_array( $this->staged_data['payment_product'], $authenticationIndicatorTypes ) ) {
				$ThreeDSecureRules = $this->getGlobal( '3DSRules' ); //ha
				if ( array_key_exists( $currency, $ThreeDSecureRules ) ) {
					if ( !is_array( $ThreeDSecureRules[$currency] ) ) {
						if ( $ThreeDSecureRules[$currency] === $country ) {
							$enable3ds = true;
						}
					} else {
						if ( empty( $ThreeDSecureRules[$currency] ) || in_array( $country, $ThreeDSecureRules[$currency] ) ) {
							$enable3ds = true;
						}
					}
				}
			}

			if ( $enable3ds ) {
				$this->log( "3dSecure enabled for $currency in $country" );
				$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['AUTHENTICATIONINDICATOR'] = '1';
			}
		} else {
			//everything that isn't cc.
			if ( (!is_null( $payment_submethod ) ) && array_key_exists( $payment_submethod, $this->payment_submethods ) && isset( $this->payment_submethods[$payment_submethod]['paymentproductid'] ) ) {
				$this->staged_data['payment_product'] = $this->payment_submethods[$payment_submethod]['paymentproductid'];
			} else {
				$this->log( "Could not find a payment product for '$payment_submethod' in payment_submethods array", LOG_ERR );
			}
		}
	}

	/**
	 * Stage the street address. In the event that there isn't anything in
	 * there, we need to send something along so that AVS checks get triggered
	 * at all. 
	 * The zero is intentional: Allegedly, Some banks won't perform the check
	 * if the address line contains no numerical data. 
	 * @param string $type request|response
	 */
	protected function stage_street( $type = 'request') {
		if ( $type === 'request' ){
			$is_garbage = false;
			$street = trim( $this->staged_data['street'] );
			( strlen( $street ) === 0 ) ? $is_garbage = true : null;
			( !DataValidator::validate_not_just_punctuation( $street ) ) ? $is_garbage = true : null;
			
			if ( $is_garbage ){
				$this->staged_data['street'] = 'N0NE PROVIDED'; //The zero is intentional. See function comment.
			}
		}
	}
	
	/**
	 * Stage the zip. In the event that there isn't anything in
	 * there, we need to send something along so that AVS checks get triggered
	 * at all. 
	 * @param string $type request|response
	 */
	protected function stage_zip( $type = 'request') {
		if ( $type === 'request' && strlen( trim( $this->staged_data['zip'] ) ) === 0  ){
			//it would be nice to check for more here, but the world has some 
			//straaaange postal codes...
			$this->staged_data['zip'] = '0';
		}
		
		//country-based zip grooming to make AVS (marginally) happy
		switch ($this->getData_Staged( 'country' ) ){
			case 'CA':
				//Canada goes "A0A 0A0"
				$this->staged_data['zip'] = strtoupper( $this->staged_data['zip'] );
				//In the event that they only forgot the space, help 'em out.
				$regex = '/[A-Z]{1}\d{1}[A-Z]{1}\d{1}[A-Z]{1}\d{1}/'; 
				if ( strlen( $this->staged_data['zip'] ) === 6 && preg_match( $regex, $this->staged_data['zip'] ) ) {
					$zip = $this->staged_data['zip'];
					$this->staged_data['zip'] = substr( $zip, 0, 3 ) . ' ' . substr( $zip, 3, 3 );
				}
				break;
		}
	}

	/**
	 * Stage branch_code for Direct Debit.
	 * Check the data constraints, and zero-pad out to that number where possible.
	 * Exceptions for the defaults are set in stage_country so we can see them all in the same place
	 * @param string $type request|response
	 */
	protected function stage_branch_code( $type = 'request' ) {
		if ( $type === 'request' && isset( $this->staged_data['branch_code'] ) ) {
			$newval = DataValidator::getZeroPaddedValue( $this->staged_data['branch_code'], $this->dataConstraints['branch_code']['length'] );
			if ( $newval ) {
				$this->staged_data['branch_code'] = $newval;
			}
		}
	}

	/**
	 * Stage bank_code for Direct Debit.
	 * Check the data constraints, and zero-pad out to that number where possible.
	 * Exceptions for the defaults are set in stage_country so we can see them all in the same place
	 * @param string $type request|response
	 */
	protected function stage_bank_code( $type = 'request' ) {
		if ( $type === 'request' && isset( $this->staged_data['bank_code'] ) ) {
			$newval = DataValidator::getZeroPaddedValue( $this->staged_data['bank_code'], $this->dataConstraints['bank_code']['length'] );
			if ( $newval ) {
				$this->staged_data['bank_code'] = $newval;
			}
		}
	}

	/**
	 * Stage account_number for Direct Debit.
	 * Check the data constraints, and zero-pad out to that number where possible.
	 * Exceptions for the defaults are set in stage_country so we can see them all in the same place
	 * @param string $type request|response
	 */
	protected function stage_account_number( $type = 'request' ) {
		if ( $type === 'request' && isset( $this->staged_data['account_number'] ) ) {
			$newval = DataValidator::getZeroPaddedValue( $this->staged_data['account_number'], $this->dataConstraints['account_number']['length'] );
			if ( $newval ) {
				$this->staged_data['account_number'] = $newval;
			}
		}
	}

	/**
	 * Stage: setupStagePaymentMethodForDirectDebit
	 *
	 * @param string	$payment_submethod
	 * @param string	$type	request|response
	 */
	protected function setupStagePaymentMethodForDirectDebit( $payment_submethod, $type = 'request' ) {

		// DATECOLLECT is required on all Direct Debit
		$this->addKeyToTransaction('DATECOLLECT');

		$this->staged_data['date_collect'] = gmdate('Ymd');
		$this->staged_data['direct_debit_text'] = 'Wikimedia Foundation';
		
		$this->var_map['COUNTRYCODEBANK'] = 'country';

		$this->dataConstraints['iban']['length'] = 21;

		// Direct debit has different required fields for each paymentproductid.
		$this->addKeysToTransactionForSubmethod( $payment_submethod );
	}

	/**
	 * Stage: setupStagePaymentMethodForEWallets
	 *
	 * @param string	$payment_submethod
	 * @param string	$type	request|response
	 */
	protected function setupStagePaymentMethodForEWallets( $payment_submethod, $type = 'request' ) {

		// DESCRIPTOR is required on WebMoney, assuming it is required for all.
		$this->addKeyToTransaction('DESCRIPTOR');

		$this->staged_data['descriptor'] = 'Wikimedia Foundation/Wikipedia';

		$this->var_map['COUNTRYCODEBANK'] = 'country';

		// eWallets custom keys
		$this->addKeysToTransactionForSubmethod( $payment_submethod );
	}
	
	/**
	 * Stage: payment_method
	 *
	 * @param string	$type	request|response
	 *
	 * @todo
	 * - Need to implement this for credit card if necessary
	 * - ISSUERID will need to provide a dropdown for rtbt_eps and rtbt_ideal.
	 * - COUNTRYCODEBANK will need it's own dropdown for country. Do not map to 'country'
	 * - DATECOLLECT is using gmdate('Ymd')
	 * - DIRECTDEBITTEXT will need to be translated. This is what appears on the bank statement for donations for a client. This is hardcoded to: Wikimedia Foundation
	 */
	protected function stage_payment_method( $type = 'request' ) {
		
		$payment_method = array_key_exists( 'payment_method', $this->staged_data ) ? $this->staged_data['payment_method']: false;
		$payment_submethod = array_key_exists( 'payment_submethod', $this->staged_data ) ? $this->staged_data['payment_submethod']: false;
		
		//Having to front-load the country in the payment submethod is pretty lame.
		//If we don't have one deliberately set...
		if (!$payment_submethod){
			$trythis = $payment_method . '_' . strtolower( $this->getData_Unstaged_Escaped('country') );
			if ( array_key_exists( $trythis, $this->payment_submethods ) ){
				$payment_submethod = $trythis;
				$this->staged_data['payment_submethod'] = $payment_submethod;
			}
		}

		// These will be grouped and ordred by payment product id
		switch ( $payment_submethod )  {
			
			/* Bank transfer */
			case 'bt':
				
				// Brazil
				if ( $this->staged_data['country'] == 'BR' ) {
					$this->dataConstraints['direct_debit_text']['city'] = 50;
				}

				// Korea - Manual does not specify North or South
				if ( $this->staged_data['country'] == 'KR' ) {
					$this->dataConstraints['direct_debit_text']['city'] = 50;
				}
				break;

			/* Direct Debit */
			case 'dd_de':
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['bank_code']['length'] = 8;
				break;
			case 'dd_nl':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['direct_debit_text']['length'] = 32;
				$this->staged_data['transaction_type'] = '01';
				break;
			case 'dd_gb':
				$this->staged_data['transaction_type'] = '01';
				break;
			case 'dd_at':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['bank_code']['length'] = 5;
				$this->dataConstraints['direct_debit_text']['length'] = 28;
				break;
			case 'dd_es':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 10;
				$this->dataConstraints['bank_code']['length'] = 4;
				$this->dataConstraints['branch_code']['length'] = 4;
				$this->dataConstraints['direct_debit_text']['length'] = 40;
				break;
			case 'dd_fr':
				$this->dataConstraints['direct_debit_text']['length'] = 18;
				break;
			case 'dd_it':
				$this->dataConstraints['account_name']['length'] = 30;
				$this->dataConstraints['account_number']['length'] = 12;
				$this->dataConstraints['bank_check_digit']['length'] = 1;
				$this->dataConstraints['bank_code']['length'] = 5;
				$this->dataConstraints['direct_debit_text']['length'] = 32;
				break;
			
			/* Cash payments */
			 case 'cash_boleto':
				$this->addKeyToTransaction('FISCALNUMBER');
				break;
			
			case 'rtbt_eps':
			case 'rtbt_ideal':

				$this->addKeysToTransactionForSubmethod( $payment_submethod );
				
				$this->addKeyToTransaction('ISSUERID');
				break;
				
			/* Default Case */
			default:
				// Nothing is done in the default case.
				// It's worth noting that at this point, it might not be an error.
				break;
		}

		switch ($payment_method) {
		case 'dd':
			$this->setupStagePaymentMethodForDirectDebit( $payment_submethod, $type);
			break;
		case 'ew':
			$this->setupStagePaymentMethodForEWallets( $payment_submethod, $type);
			break;
		}
	}

	/**
	 * Stage: recurring
	 * Adds the recurring payment pieces to the structure of 
	 * INSERT_ORDERWITHPAYMENT if the recurring field is populated.
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_recurring( $type = 'request' ){
		if ( ! $this->getData_Staged( 'recurring' ) ){
			return;
		} else {
			$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'ORDERTYPE';
			$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['ORDERTYPE'] = '4';
		}
	}
	
	/**
	 * Stage: country
	 * This should be a catch-all for establishing weird country-based rules. 
	 * Right now, we only have the one, but there could be more here later.
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_country( $type = 'request' ){
		if ( $type != 'request' ){
			return; //nothing to do here. 
		}
		
		switch ( $this->getData_Staged( 'country' ) ){
			case 'AR' :
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'USAGETYPE';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['ORDER'][] = 'PURCHASETYPE';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['USAGETYPE'] = '0';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['values']['PURCHASETYPE'] = '1';
				break;
		}
	}

	protected function stage_contribution_tracking_id( $type = 'request' ) {
		$ctid = $this->staged_data['contribution_tracking_id'];
		if ( $type === 'request' ) {
			//append timestamp to ctid
			$ctid .= '.' . (time() % 100000); //least significant five
		} elseif ( $type === 'response' ) {
			$ctid = explode( '.', $ctid );
			$ctid = $ctid[0];
		}
		$this->staged_data['contribution_tracking_id'] = $ctid;
	}

	protected function stage_fiscal_number( $type = 'request' ) {
		if ( $type != 'request' ){
			return; //nothing to do here. 
		}
		
		$this->staged_data['fiscal_number'] = preg_replace( "/[\.\/\-]/", "", $this->getData_Staged( 'fiscal_number' ) );
	}

	/**
	 * Add keys to transaction for submethod
	 *
	 */
	protected function addKeysToTransactionForSubmethod( $payment_submethod ) {

		// If there are no keys to add, do not proceed.
		if ( !is_array( $this->payment_submethods[ $payment_submethod ]['keys'] ) ) {
			
			return;
		}
		
		foreach ( $this->payment_submethods[ $payment_submethod ]['keys'] as $key ) {

			$this->addKeyToTransaction( $key );
		}
	}
	
	/**
	 * Stage: returnto
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_returnto( $type = 'request' ) {
		
		if ( $type === 'request' ) {

			// Get the default returnto
			$returnto = $this->getData_Staged( 'returnto' );
			
			if ( $this->getData_Unstaged_Escaped( 'payment_method' ) === 'cc' ){
				
				// Add order ID to the returnto URL, only if it's not already there. 
				//TODO: This needs to be more robust (like actually pulling the 
				//qstring keys, resetting the values, and putting it all back)
				//but for now it'll keep us alive. 
				if ( $this->getOrderIDMeta( 'generate' ) && !is_null( $returnto ) && !strpos( $returnto, 'order_id' ) ) {
					$queryArray = array( 'order_id' => $this->staged_data['order_id'] );
					$this->staged_data['returnto'] = wfAppendQuery( $returnto, $queryArray );
				}
				
			} else {
				
				// Do we want to set this here?
				$this->staged_data['returnto'] = $this->getThankYouPage();
			}
		}
	}
	
	/**
	 * post-process function for INSERT_ORDERWITHPAYMENT. 
	 * This gets called by executeIfFunctionExists, in do_transaction. 
	 */
	protected function post_process_insert_orderwithpayment(){
		//yeah, we absolutely want to do this for every one of these. 
		if ( $this->getTransactionStatus() === true ) {
			$data = $this->getTransactionData();
			$action = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] );
			if ($action != 'failed'){
				$this->doLimboStompTransaction();
			}
		}
	}
	
	protected function pre_process_get_orderstatus(){
		static $checked = array();
		$oid = $this->getData_Unstaged_Escaped('order_id');
		if  ( $this->getData_Unstaged_Escaped( 'payment_method' ) === 'cc' && !in_array( $oid, $checked ) ){
			$this->runPreProcessHooks();
			$checked[] = $oid;
		}
	}

	/**
	 * getCVVResult is intended to be used by the functions filter, to 
	 * determine if we want to fail the transaction ourselves or not. 
	 */
	public function getCVVResult(){
		if ( is_null( $this->getData_Unstaged_Escaped( 'cvv_result' ) ) ){
			return null;
		}
		
		$cvv_map = $this->getGlobal( 'CvvMap' );
		
		$result = $cvv_map[$this->getData_Unstaged_Escaped( 'cvv_result' )];
		return $result;

	}	
	
	/**
	 * getAVSResult is intended to be used by the functions filter, to 
	 * determine if we want to fail the transaction ourselves or not. 
	 */
	public function getAVSResult(){
		if ( is_null( $this->getData_Unstaged_Escaped( 'avs_result' ) ) ){
			return null;
		}
		//Best guess here: 
		//Scale of 0 - 100, of Problem we think this result is likely to cause.

		$avs_map = $this->getGlobal( 'AvsMap' );

		$result = $avs_map[$this->getData_Unstaged_Escaped( 'avs_result' )];
		return $result;
	}
}
