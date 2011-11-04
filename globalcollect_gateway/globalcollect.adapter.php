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
	 * Define var_map
	 *
	 * @todo
	 * - RETURNURL: Find out where the returnto URL is supposed to be coming from.
	 * - IPADDRESS: Is the server IPA or the user/client IPA?
	 */
	public function defineVarMap() {
		
		$this->var_map = array(
			'ACCOUNTNAME'		=> 'account_name',
			'ACCOUNTNUMBER'		=> 'account_number',
			'AUTHORIZATIONID'	=> 'authorization_id',
			'AMOUNT'			=> 'amount',
			'BANKCHECKDIGIT'	=> 'bank_check_digit',
			'BANKCODE'			=> 'bank_code',
			'BANKNAME'			=> 'bank_name',
			'BRANCHCODE'		=> 'branch_code',
			'CITY'				=> 'city',
			'COUNTRYCODE'		=> 'country',
			'COUNTRYCODEBANK'	=> 'country_code_bank',
			'CREDITCARDNUMBER'	=> 'card_num',
			'CURRENCYCODE'		=> 'currency',
			'CVV'				=> 'cvv',
			'DATECOLLECT'		=> 'date_collect',
			'DIRECTDEBITTEXT'	=> 'direct_debit_text',
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
			'RETURNURL'			=> 'returnto',
			'STATE'				=> 'state',
			'STREET'			=> 'street',
			'SURNAME'			=> 'lname',
			'TRANSACTIONTYPE'	=> 'transaction_type',
			'ZIP'				=> 'zip',
		);
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
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending_poke', 525 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'pending', 550, 650 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 800, 975 ); //these are all post-authorized, but technically pre-settled...
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'complete', 1000, 1050 );
		$this->addCodeRange( 'GET_ORDERSTATUS', 'STATUSID', 'failed', 1100, 99999 );
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
			'loop_for_status' => array(
				//'pending',
				'pending_poke',
				'complete',
				'failed',
				'revised',
			)
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
			'types'	=> array( 'dd_johnsen_nl', 'dd_johnsen_de', 'dd_johnsen_at', 'dd_johnsen_fr', 'dd_johnsen_gb', 'dd_johnsen_be', 'dd_johnsen_ch', 'dd_johnsen_it', 'dd_johnsen_es', ),
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
		 * Direct Debit
		 */
		 
		// Bank Transfer
		$this->payment_submethods['dd_johnsen_nl'] = array(
			'paymentproductid'	=> 711,
			'label'	=> 'Bank Transfer',
			'group'	=> 'dd',
			'validation' => array(),
		);
		 
		// Bank Transfer
		$this->payment_submethods['dd_johnsen_de'] = array(
			'paymentproductid'	=> 711,
			'label'	=> 'Bank Transfer',
			'group'	=> 'dd',
			'validation' => array(),
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
		self::log( $this->getData( 'contribution_tracking_id' ) . ": Raw XML Response:\n" . $displayXML ); //I am apparently a huge fibber.
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
			
			// If we have a localized version of the error message, output that
			if ( substr( wfMsg( 'globalcollect_gateway-response-'.$code ), 0, 4 ) === '&lt;' ) {
				$errors[$code] = $message;
			} else {
				$errors[$code] = wfMsg( 'globalcollect_gateway-response-'.$code );
			}
			
		}
		return $errors;
	}

	/**
	 * Harvest the data we need back from the gateway. 
	 * return a key/value array
	 *
	 * @param array	$response	The response array
	 */
	public function getResponseData( $response ) {
		$data = array( );

		$transaction = $this->getCurrentTransaction();

		switch ( $transaction ) {
			case 'INSERT_ORDERWITHPAYMENT':
				$data = $this->xmlChildrenToArray( $response, 'ROW' );
				$data['ORDER'] = $this->xmlChildrenToArray( $response, 'ORDER' );
				$data['PAYMENT'] = $this->xmlChildrenToArray( $response, 'PAYMENT' );
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
		$this->setTransactionResult( $this->getData( 'order_id' ), 'gateway_txn_id' );
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
				$this->postdata['amount'] = $this->postdata['amount'] * 100;
				break;
			case 'response':
				$this->postdata['amount'] = $this->postdata['amount'] / 100;
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
		if ( array_key_exists( 'card_num', $this->postdata ) ) {
			$this->postdata['card_num'] = str_replace( ' ', '', $this->postdata['card_num'] );
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

		if ( ( array_key_exists( 'card_type', $this->postdata ) ) && array_key_exists( $this->postdata['card_type'], $types ) ) {
			$this->postdata['card_type'] = $types[$this->postdata['card_type']];
		} else {
			//$this->postdata['card_type'] = '';
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
	 */
	protected function stage_payment_method( $type = 'request' ) {
		
		$payment_method = array_key_exists( 'payment_method', $this->postdata ) ? $this->postdata['payment_method']: false;
		$payment_submethod = array_key_exists( 'payment_submethod', $this->postdata ) ? $this->postdata['payment_submethod']: false;

		// These will be grouped and ordred by payment product id
		switch ( $payment_submethod )  {
			
			/* Bank transfer */
			case 'bt':
				$this->postdata['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				break;

			/* Direct Debit */
			case 'dd_johnsen_nl':
			case 'dd_johnsen_de':
			case 'dd_johnsen_at':
			case 'dd_johnsen_fr':
			case 'dd_johnsen_gb':
			case 'dd_johnsen_be':
			case 'dd_johnsen_ch':
			case 'dd_johnsen_it':
			case 'dd_johnsen_es':
				$this->postdata['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				$this->var_map['COUNTRYCODEBANK'] = 'country';

				// Currently, this is needed by the Netherlands
				$this->postdata['transaction_type'] = '01';

				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'ACCOUNTNAME';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'ACCOUNTNUMBER';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'AUTHORIZATIONID';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'BANKCHECKDIGIT';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'BANKCODE';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'BANKNAME';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'BRANCHCODE';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'COUNTRYCODEBANK';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'DATECOLLECT';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'DIRECTDEBITTEXT';
				//$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'IBAN';
				$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'TRANSACTIONTYPE';

				break;
			
			/* Real time bank transfer */
			case 'rtbt_nordea_sweeden':
			case 'rtbt_enets':
			case 'rtbt_sofortuberweisung':
				$this->postdata['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				break;
			
			case 'rtbt_eps':
			case 'rtbt_ideal':
				$this->postdata['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				
				// Add the ISSUERID field if it does not exist
				if ( !in_array( 'ISSUERID', $this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'] ) ) {
					$this->transactions['INSERT_ORDERWITHPAYMENT']['request']['REQUEST']['PARAMS']['PAYMENT'][] = 'ISSUERID';
				}
				break;
				
			/* Default Case */
			default:
				//$this->postdata['payment_product'] = $this->payment_submethods[ $payment_submethod ]['paymentproductid'];
				//$this->var_map['PAYMENTPRODUCTID'] = 'payment_product';
				break;
		}
	}
	
	/**
	 * Stage: returnto
	 *
	 * @param string	$type	request|response
	 */
	protected function stage_returnto( $type = 'request' ) {
		if ( $type === 'request' ) {
			// Add order ID to the returnto URL
			$queryArray = array( 'order_id' => $this->postdata['order_id'] );
			$this->postdata['returnto'] = wfAppendQuery( $this->postdata['returnto'], $queryArray );
		}
	}
	
	protected function pre_process_insert_orderwithpayment(){
		if ( $this->getData( 'payment_method' ) === 'cc' ){
			$this->runPreProcessHooks(); //this is shortly to move elsewhere.  
			$this->addDonorDataToSession();
		}
	}
	
	protected function pre_process_get_orderstatus(){
		if  ( $this->getData( 'payment_method' ) === 'cc' ){
			//if they're set, get CVVRESULT && AVSRESULT
			global $wgRequest;
			$cvv_result = $wgRequest->getVal( 'CVVRESULT', null );
			$avs_result = $wgRequest->getVal( 'AVSRESULT', null );
			if ( !is_null($cvv_result) ){
				$this->debugarray[] = "CVV result: $cvv_result";
			}
			if ( !is_null($avs_result) ){
				$this->debugarray[] = "AVS result: $avs_result";
			}
		}
	}
	
	protected function post_process_get_orderstatus(){
		$this->runPostProcessHooks();
	}

}