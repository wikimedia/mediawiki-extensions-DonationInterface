<?php

class GlobalCollectAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Global Collect';
	const IDENTIFIER = 'globalcollect';
	const COMMUNICATION_TYPE = 'xml';
	const GLOBAL_PREFIX = 'wgGlobalCollectGateway';

	/**
	 * Define accountInfo
	 */
	public function defineAccountInfo() {
		$this->accountInfo = array(
			'MERCHANTID' => self::getGlobal( 'MerchantID' ),
			//'IPADDRESS' => '', //TODO: Not sure if this should be OUR ip, or the user's ip. Hurm. 
			'VERSION' => "1.0",
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
			430452	=> 'globalcollect_gateway-response-default', // Not authorised :: This message was generated when trying to attempt a direct debit transaction from Belgium.	
			430900	=> 'globalcollect_gateway-response-default', // NO VALID PROVIDERS FOUND FOR COMBINATION MERCHANTID: NNNN, PAYMENTPRODUCT: NNN, COUNTRYCODE: XX, CURRENCYCODE: XXX	
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
			'AUTHORIZATIONID'	=> 'authorization_id',
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
			'CURRENCYCODE'		=> 'currency',
			'CVV'				=> 'cvv',
			'DATECOLLECT'		=> 'date_collect',
			'DIRECTDEBITTEXT'	=> 'direct_debit_text',
			'DOMICILIO'			=> 'domicilio', // dd:ES
			'EFFORTID'			=> 'effort_id',
			'EMAIL'				=> 'email',
			'EXPIRYDATE'		=> 'expiration',
			'FIRSTNAME'			=> 'fname',
			'IBAN'				=> 'iban',
			'IPADDRESS'			=> 'user_ip',
			'ISSUERID'			=> 'issuer_id',
			'LANGUAGECODE'		=> 'language',
			'MERCHANTREFERENCE'	=> 'order_id',
			'ORDERID'			=> 'order_id',
			'PAYMENTPRODUCTID'	=> 'card_type',
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
		);
	}
	
	/**
	 * Setting some GC-specific defaults. 
	 * @param array $options These get extracted in the parent.
	 */
	function setPostDefaults( $options = array() ) {
		parent::setPostDefaults( $options );
		$this->postdatadefaults['attempt_id'] = '1';
		$this->postdatadefaults['effort_id'] = '1';
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
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 200 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 220, 280 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 300 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 310, 350 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'revised', 400 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending-poke', 525 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 550, 650 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 800, 975 ); //these are all post-authorized, but technically pre-settled...
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 1000, 1050 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 1100, 99999 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 100000, 999999 ); // 102020 - ACTION 130 IS NOT ALLOWED FOR MERCHANT NNN, IPADDRESS NNN.NNN.NNN.NNN
		
		
		$this->defineGoToThankYouOn();
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
	 * - Does DO_BANKVALIDATION need IPADDRESS? What about the other transactions. Is this the user's IPA?
	 * - Does DO_BANKVALIDATION need HOSTEDINDICATOR?
	 *
	 * This method should define:
	 * - DO_BANKVALIDATION: used prior to INSERT_ORDERWITHPAYMENT for direct debit
	 * - INSERT_ORDERWITHPAYMENT: used for payments
	 * - TEST_CONNECTION: testing connections - is this still valid?
	 * - GET_ORDERSTATUS
	 */
	public function defineTransactions() {

		// Define the transaction types and groups
		$this->definePaymentMethods();
		$this->definePaymentSubmethods();
		
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
							'AUTHORIZATIONID',
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
	}
	
	/**
	 * Define payment methods
	 *
	 * The credit card group has a catchall for unspecified payment types.
	 */
	protected function definePaymentMethods() {
		
		$this->payment_methods = array();
		
		// Bank Transfers
		$this->payment_methods['bt'] = array(
			'label'	=> 'Bank transfer',
			'types'	=> array( 'bt', ),
			'validation' => array( 'creditCard' => false, )
			//'forms'	=> array( 'Gateway_Form_TwoStepAmount', ),
		);
		
		// Credit Cards
		$this->payment_methods['cc'] = array(
			'label'	=> 'Credit Cards',
			'types'	=> array( '', 'visa', 'mc', 'amex', 'discover', 'maestro', 'solo', 'laser', 'jcb', 'cb', ),
		);
		
		// Direct Debit
		$this->payment_methods['dd'] = array(
			'label'	=> 'Direct Debit',
			'types'	=> array( 'dd_at', 'dd_be', 'dd_ch', 'dd_de', 'dd_es','dd_fr', 'dd_gb', 'dd_it', 'dd_nl', ),
			'validation' => array( 'creditCard' => false, )
			//'forms'	=> array( 'Gateway_Form_TwoStepAmount', ),
		);
		
		// Real Time Bank Transfers
		$this->payment_methods['rtbt'] = array(
			'label'	=> 'Real time bank transfer',
			'types'	=> array( 'rtbt_ideal', 'rtbt_eps', 'rtbt_sofortuberweisung', 'rtbt_nordea_sweeden', 'rtbt_enets', ),
		);
	}

	/**
	 * Define payment submethods
	 *
	 */
	protected function definePaymentSubmethods() {
		
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
		);

		/*
		 * Bank transfers
		 */
		 
		// Bank Transfer
		$this->payment_submethods['bt'] = array(
			'paymentproductid'	=> 11,
			'label'	=> 'Bank Transfer',
			'group'	=> 'bt',
			'validation' => array(),
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
		);
		 
		// MasterCard
		$this->payment_submethods['mc'] = array(
			'paymentproductid'	=> 3,
			'label'	=> 'MasterCard',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// American Express
		$this->payment_submethods['amex'] = array(
			'paymentproductid'	=> 2,
			'label'	=> 'American Express',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// Maestro
		$this->payment_submethods['maestro'] = array(
			'paymentproductid'	=> 117,
			'label'	=> 'Maestro',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// Solo
		$this->payment_submethods['solo'] = array(
			'paymentproductid'	=> 118,
			'label'	=> 'Solo',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// Laser
		$this->payment_submethods['laser'] = array(
			'paymentproductid'	=> 124,
			'label'	=> 'Laser',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// JCB
		$this->payment_submethods['jcb'] = array(
			'paymentproductid'	=> 125,
			'label'	=> 'JCB',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// Discover
		$this->payment_submethods['discover'] = array(
			'paymentproductid'	=> 128,
			'label'	=> 'Discover',
			'group'	=> 'cc',
			'validation' => array(),
		);
		 
		// CB
		$this->payment_submethods['cb'] = array(
			'paymentproductid'	=> 130,
			'label'	=> 'CB', // Carte Bancaire OR Carte Bleue
			'group'	=> 'cc',
			'validation' => array(),
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
			'paymentproductid'	=> 713,
			'label'	=> 'Direct debit: AT',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', 'BANKNAME', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: BE
		$this->payment_submethods['dd_be'] = array(
			'paymentproductid'	=> 716,
			'label'	=> 'Direct debit: BE',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'AUTHORIZATIONID', 'BANKNAME', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: CH
		$this->payment_submethods['dd_ch'] = array(
			'paymentproductid'	=> 717,
			'label'	=> 'Direct debit: CH',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'ADDRESSLINE1E', 'ADDRESSLINE2', 'ADDRESSLINE3', 'ADDRESSLINE4', 'BANKCODE', 'BANKNAME', 'CUSTOMERBANKCITY', 'CUSTOMERBANKNUMBER', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP', 'DIRECTDEBITTEXT', 'IBAN', ),
		);
		 
		// Direct debit: DE
		$this->payment_submethods['dd_de'] = array(
			'paymentproductid'	=> 712,
			'label'	=> 'Direct debit: DE',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', 'BANKNAME', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: ES
		$this->payment_submethods['dd_es'] = array(
			'paymentproductid'	=> 719,
			'label'	=> 'Direct debit: ES',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', 'BANKNAME', 'BRANCHCODE', 'BANKCHECKDIGIT', 'CUSTOMERBANKCITY', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP', 'DIRECTDEBITTEXT', 'DOMICILIO', 'PROVINCIA', ),
		);
		 
		// Direct debit: FR
		$this->payment_submethods['dd_fr'] = array(
			'paymentproductid'	=> 714,
			'label'	=> 'Direct debit: FR',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', 'BANKNAME', 'BRANCHCODE', 'BANKCHECKDIGIT', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: GB
		$this->payment_submethods['dd_gb'] = array(
			'paymentproductid'	=> 715,
			'label'	=> 'Direct debit: GB',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNUMBER', 'AUTHORIZATIONID', 'BANKCODE', 'BANKNAME', 'DIRECTDEBITTEXT', 'TRANSACTIONTYPE', ),
		);
		 
		// Direct debit: IT
		$this->payment_submethods['dd_it'] = array(
			'paymentproductid'	=> 718,
			'label'	=> 'Direct debit: IT',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKCODE', 'BANKNAME', 'BRANCHCODE', 'BANKAGENZIA', 'BANKCHECKDIGIT', 'BANKFILIALE', 'CUSTOMERBANKCITY', 'CUSTOMERBANKNUMBER', 'CUSTOMERBANKSTREET', 'CUSTOMERBANKZIP', 'DIRECTDEBITTEXT', ),
		);
		 
		// Direct debit: NL
		$this->payment_submethods['dd_nl'] = array(
			'paymentproductid'	=> 711,
			'label'	=> 'Direct debit: NL',
			'group'	=> 'dd',
			'validation' => array(),
			'keys' => array( 'ACCOUNTNAME', 'ACCOUNTNUMBER', 'BANKNAME', 'DIRECTDEBITTEXT', 'TRANSACTIONTYPE', ),
		);

		/*
		 * Real time bank transfers
		 */
		 
		// Nordea (Sweeden)
		$this->payment_submethods['rtbt_nordea_sweeden'] = array(
			'paymentproductid'	=> 805,
			'label'	=> 'Nordea (Sweeden)',
			'group'	=> 'rtbt',
			'validation' => array(),
		);
		 
		// Ideal
		$this->payment_submethods['rtbt_ideal'] = array(
			'paymentproductid'	=> 809,
			'label'	=> 'Ideal',
			'group'	=> 'rtbt',
			'validation' => array(),
			'issuerids' => array( 
				771	=> 'RegioBank',
				161	=> 'Van Lanschot Bankiers',
				31	=> 'ABN AMRO',
				761	=> 'ASN Bank',
				21	=> 'Rabobank',
				511	=> 'Triodos Bank',
				721	=> 'ING',
				751	=> 'SNS Bank',
				91	=> 'Friesland Bank',
			)
		);
		 
		// eNETS
		$this->payment_submethods['rtbt_enets'] = array(
			'paymentproductid'	=> 810,
			'label'	=> 'eNETS',
			'group'	=> 'rtbt',
			'validation' => array(),
		);
		 
		// Sofortuberweisung/DIRECTebanking
		$this->payment_submethods['rtbt_sofortuberweisung'] = array(
			'paymentproductid'	=> 836,
			'label'	=> 'Sofortuberweisung/DIRECTebanking',
			'group'	=> 'rtbt',
			'validation' => array(),
		);
		 
		// eps Online-Überweisung
		$this->payment_submethods['rtbt_eps'] = array(
			'paymentproductid'	=> 856,
			'label'	=> 'eps Online-Überweisung',
			'group'	=> 'rtbt',
			'validation' => array(),
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
	}

	/**
	 * Get payment method meta
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @param	string	$payment_method	Payment methods contain payment submethods
	 */
	public function getPaymentMethodMeta( $payment_method ) {
		
		if ( isset( $this->payment_methods[ $payment_method ] ) ) {
			
			return $this->payment_methods[ $payment_method ];
		}
		else {
			$message = 'The payment method [ ' . $payment_method . ' ] was not found.';
			throw new Exception( $message );
		}
	}
	
	/**
	 * Get payment submethod meta
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @param	string	$payment_submethod	Payment submethods are mapped to paymentproductid
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
			throw new Exception( $message );
		}
	}
	
	/**
	 * Get payment submethod form validation options
	 *
	 * @todo
	 * - These may need to move to the parent class
	 *
	 * @return	array
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
	public function do_transaction( $transaction ){
		switch ( $transaction ){
			case 'Confirm_CreditCard' :
				return $this->transactionConfirm_CreditCard();
				break;
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
		if ( count( $addme ) ){
			$this->addData( $addme );
		}
		$logmsg = $this->getData_Raw( 'contribution_tracking_id' ) . ': ';
		$logmsg .= 'CVV Result: ' . $this->getData_Raw( 'cvv_result' );
		$logmsg .= ', AVS Result: ' . $this->getData_Raw( 'avs_result' );
		self::log( $logmsg );
		
		$status_result = $this->do_transaction( 'GET_ORDERSTATUS' );
		
		//error_log( "GET_ORDERSTATUS result: " . $status_result );
		
		$cancelflag = false; //this will denote the thing we're trying to do with the donation attempt
		$problemflag = false; //this will get set to true, if we can't continue and need to give up and just log the hell out of it. 
		$problemmessage = ''; //to be used in conjunction with the flag.
		
		//we filtered
		if ( array_key_exists( 'action', $status_result ) && $status_result['action'] != 'process' ){
			$cancelflag = true;
		} elseif ( array_key_exists( 'status', $status_result ) && $status_result['status'] === false ) {
		//can't communicate or internal error
			$problemflag = true;
		}

		if ( !$cancelflag && !$problemflag ) {
			$order_status_results = $this->getTransactionWMFStatus();
			if (!$order_status_results){
				$problemflag = true;
				$problemmessage = "We don't have a Transaction WMF Status after doing a GET_ORDERSTATUS.";
			}
			switch ( $order_status_results ){
				//status says no - probably no need to cancel, but why not be explicit? 
				case 'failed' : 
				case 'revised' :  
					$cancelflag = true;
					break;
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
			
			if ( !$cancelflag ){
				$final = $this->do_transaction( 'SET_PAYMENT' );
				if ( isset( $final['status'] ) && $final['status'] === true ) {
					$this->setTransactionWMFStatus( $order_status_results ); //this had damn well better exist if we got this far.
					$this->runPostProcessHooks();  //stomp is in here
					$this->unsetAllSessionData();
				} else {
					$problemflag = true;
					$problemmessage = "SET_PAYMENT couldn't communicate properly!";
				}
			} else {
				$final = $this->do_transaction( 'CANCEL_PAYMENT' );
				if ( isset( $final['status'] ) && $final['status'] === true ) {
					$this->setTransactionWMFStatus( 'failed' );
					$this->unsetAllSessionData();
				} else {
					$problemflag = true;
					$problemmessage = "CANCEL_PAYMENT couldn't communicate properly!";
				}
			}
		}
		
		if ( $problemflag ){
			//we have probably had a communication problem that could mean stranded payments. 
			$problemmessage = $this->getData_Raw( 'contribution_tracking_id' ) . ':' . $this->getData_Raw( 'order_id' ) . ' ' . $problemmessage;
			self::log( $problemmessage );
			//hurm. It would be swell if we had a message that told the user we had some kind of internal error. 
			return array(
				'status' => false,
				//TODO: appropriate messages. 
				'message' => $problemmessage,
				'errors' => array(
					'1000000' => 'Transaction could not be processed due to an internal error.'
				),
				'action' => $this->getValidationAction(),
			);
		}
		
//		return something better... if we need to!
		return $status_result;
	}
	
	/**
	 * Take the entire response string, and strip everything we don't care about.
	 * For instance: If it's XML, we only want correctly-formatted XML. Headers must be killed off. 
	 * return a string.
	 *
	 * @param string	$rawResponse	The raw response a string of XML.
	 */
	public function getFormattedResponse( $rawResponse ) {
		$xmlString = $this->stripXMLResponseHeaders( $rawResponse );
		$displayXML = $this->formatXmlString( $xmlString );
		$realXML = new DomDocument( '1.0' );
		self::log( $this->getData_Raw( 'contribution_tracking_id' ) . ": Raw XML Response:\n" . $displayXML ); //I am apparently a huge fibber.
		$realXML->loadXML( trim( $xmlString ) );
		return $realXML;
	}

	/**
	 * Parse the response to get the status. Not sure if this should return a bool, or something more... telling.
	 *
	 * @param array	$response	The response array
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
				}
			}

			$errors[ $code ] = ( $this->getGlobal( 'DisplayDebug' ) ) ? '*** ' . $message : $this->getErrorMapByCodeAndTranslate( $code );

			$this->setTransactionWMFStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $code ) );
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
	 * @param array	$response	The response array
	 */
	public function getResponseData( $response ) {
		$data = array( );

		$transaction = $this->getCurrentTransaction();

		$this->getTransactionStatus();
		
		switch ( $transaction ) {
			case 'INSERT_ORDERWITHPAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				$data['PAYMENT'] = $this->xmlChildrenToArray( $response, 'PAYMENT' );

				// WMFStatus will already be set if the transaction was unable to communicate properly.
				if ( $this->getTransactionStatus() ) {
					$this->setTransactionWMFStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] ) );
				}

				break;
			case 'GET_ORDERSTATUS':
				$data = $this->xmlChildrenToArray( $response, 'STATUS' );
				$this->setTransactionWMFStatus( $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $data['STATUSID'] ) );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				break;
		}

		return $data;
	}
	
	/**
	 * Gets all the currency codes appropriate for this gateway
	 * @return array of currency codes
	 */
	function getCurrencies() {
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
			'BSD', // Bahamian dollar
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
			'KYD', // Cayman Islands dollar
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
			'NOK', // Norwegian krone
			'NZD', // New Zealand dollar
			'OMR', // Omani rial
			'PAB', // Panamanian balboa
			'PEN', // Peru nuevo sol
			'PHP', // Philippine peso
			'PKR', // Pakistani rupee
			'PLN', // Polish złoty
			'PYG', // Paraguayan guaraní
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
			'UZS', // Uzbekistani som
			'VND', // Vietnamese dong
			'XAF', // Central African CFA franc
			'XCD', // East Caribbean dollar
			'XOF', // West African CFA franc
			'ZAR', // South African rand
		);
		return $currencies;
	}

	/**
	 * Process the response
	 *
	 * @param array	$response	The response array
	 */
	public function processResponse( $response ) {
		//set the transaction result message
		$responseStatus = isset( $response['STATUSID'] ) ? $response['STATUSID'] : '';
		$this->setTransactionResult( "Response Status: " . $responseStatus, 'txn_message' ); //TODO: Translate for GC. 
		$this->setTransactionResult( $this->getData_Raw( 'order_id' ), 'gateway_txn_id' );
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
			'card_type',
			//'card_num',
			'returnto',
			'payment_method',
			'payment_submethod',
			'issuer_id',
			'order_id', //This may or may not oughta-be-here...
		);
	}

	/**
	 * Stage: amount
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_amount( $type = 'request' ) {
		switch ( $type ) {
			case 'request':
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
	 * Stage: card_type
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_card_type( $type = 'request' ) {

		$types = array(
			'visa' => '1',
			'american' => '2',
			'amex' => '2',
			'american express' => '2',
			'mastercard' => '3',
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

		$card_type = $this->getData_Staged('card_type');
		if ( ( !is_null( $card_type ) ) && array_key_exists( $card_type, $types ) ) {
			$this->staged_data['card_type'] = $types[$card_type];
		} else {
			//$this->staged_data['card_type'] = '';
			//iono: maybe nothing? 
		}
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

		// These will be grouped and ordred by payment product id
		switch ( $payment_submethod )  {
			
			/* Bank transfer */
			case 'bt':
				$this->staged_data['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				break;

			/* Direct Debit */
			case 'dd_nl':
			case 'dd_gb':
				$this->staged_data['transaction_type'] = '01';
			case 'dd_at':
			case 'dd_be':
			case 'dd_ch':
			case 'dd_de':
			case 'dd_es':
			case 'dd_fr':
			case 'dd_it':

				// DATECOLLECT is required on all Direct Debit
				$this->addKeyToTransaction('DATECOLLECT');

				$this->staged_data['date_collect'] = gmdate('Ymd');
				$this->staged_data['direct_debit_text'] = 'Wikimedia Foundation';
				
				$this->staged_data['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				$this->var_map['COUNTRYCODEBANK'] = 'country';

				// Direct debit has different required fields for each paymentproductid.
				$this->addKeysToTransactionForSubmethod( $payment_submethod );

				break;
			
			/* Real time bank transfer */
			case 'rtbt_nordea_sweeden':
			case 'rtbt_enets':
			case 'rtbt_sofortuberweisung':
				$this->staged_data['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				break;
			
			case 'rtbt_eps':
			case 'rtbt_ideal':
				$this->staged_data['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				
				$this->addKeyToTransaction('ISSUERID');
				break;
				
			/* Default Case */
			default:
				
				// Nothing is done in the default case.
				break;
		}
	}
	
	/**
	 * Add keys to transaction for submethod
	 *
	 */
	protected function addKeysToTransactionForSubmethod( $payment_submethod ) {
		
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
			// Add order ID to the returnto URL, only if it's not already there. 
			//TODO: This needs to be more robust (like actually pulling the 
			//qstring keys, resetting the values, and putting it all back)
			//but for now it'll keep us alive. 
			$returnto = $this->getData_Staged( 'returnto' );
			if ( !is_null( $returnto ) && !strpos( $returnto, 'order_id' ) ){
				$queryArray = array( 'order_id' => $this->staged_data['order_id'] );
				$this->staged_data['returnto'] = wfAppendQuery( $returnto, $queryArray );
			}
		}
	}
	
	protected function pre_process_insert_orderwithpayment(){
		$this->incrementNumAttempt();
		if ( $this->getData_Raw( 'payment_method' ) === 'cc' ){
			$this->addDonorDataToSession();
		}
	}
	
	protected function pre_process_get_orderstatus(){
		if  ( $this->getData_Raw( 'payment_method' ) === 'cc' ){
			$this->runPreProcessHooks();
		}
	}
	
	protected function post_process_insert_orderwithpayment(){
		if  ( $this->getData_Raw( 'payment_method' ) != 'cc' ){
			$this->runPostProcessHooks();
		}
	}
	
	/**
	 * getCVVResult is intended to be used by the functions filter, to 
	 * determine if we want to fail the transaction ourselves or not. 
	 */
	public function getCVVResult(){
		if ( is_null( $this->getData_Raw( 'cvv_result' ) ) ){
			return null;
		}
		
		$cvv_map = $this->getGlobal( 'CvvMap' );
		
		$result = $cvv_map[$this->getData_Raw( 'cvv_result' )];
		return $result;

	}	
	
	/**
	 * getAVSResult is intended to be used by the functions filter, to 
	 * determine if we want to fail the transaction ourselves or not. 
	 */
	public function getAVSResult(){
		if ( is_null( $this->getData_Raw( 'avs_result' ) ) ){
			return null;
		}
		//Best guess here: 
		//Scale of 0 - 100, of Problem we think this result is likely to cause.

		$avs_map = $this->getGlobal( 'AvsMap' );

		$result = $avs_map[$this->getData_Raw( 'avs_result' )];
		return $result;
	}

}