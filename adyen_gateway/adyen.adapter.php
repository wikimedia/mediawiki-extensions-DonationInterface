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
use Psr\Log\LogLevel;

/**
 * AdyenAdapter
 *
 */
class AdyenAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Adyen';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenGateway';

	public function getCommunicationType() {
		return 'namevalue';
	}

	public function getRequiredFields() {
		$fields = parent::getRequiredFields();
		$fields[] = 'address';
		$fields[] = 'payment_submethod';
		return $fields;
	}

	function defineAccountInfo() {
		$this->accountInfo = array(
			'merchantAccount' => $this->account_config[ 'AccountName' ],
			'skinCode' => $this->account_config[ 'SkinCode' ],
			'hashSecret' => $this->account_config[ 'SharedSecret' ],
		);
	}

	function defineDataConstraints() {
	}

	function defineErrorMap() {
		$this->error_map = array(
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
		);
	}

	function defineStagedVars() {
		$this->staged_vars = array(
			'amount',
			'full_name',
			'street',
			'zip',
			'risk_score',
			'hpp_signature' // Keep this at the end - it depends on the rest
		);
	}

	/**
	 * Define var_map
	 */
	function defineVarMap() {
		$this->var_map = array(
			'allowedMethods' => 'allowed_methods',
			'billingAddress.city' => 'city',
			'billingAddress.country' => 'country',
			'billingAddress.postalCode' => 'zip',
			'billingAddress.stateOrProvince' => 'state',
			'billingAddress.street' => 'street',
			'billingAddressType' => 'billing_address_type',
			'blockedMethods' => 'blocked_methods',
			'card.cardHolderName' => 'full_name',
			'currencyCode' => 'currency_code',
			'deliveryAddressType' => 'delivery_address_type',
			'merchantAccount' => 'merchant_account',
			'merchantReference' => 'order_id',
			'merchantReturnData' => 'return_data',
			'merchantSig' => 'hpp_signature',
			'offset' => 'risk_score',
			'orderData' => 'order_data',
			'paymentAmount' => 'amount',
			'pspReference' => 'gateway_txn_id',
			'recurringContract' => 'recurring_type',
			'sessionValidity' => 'session_expiration',
			'shipBeforeDate' => 'expiration',
			'shopperEmail' => 'email',
			'shopperLocale' => 'language',
			'shopperReference' => 'customer_id',
			'shopperStatement' => 'statement_template',
			'skinCode' => 'skin_code',
		);
	}

	function defineReturnValueMap() {
		$this->return_value_map = array(
			'authResult' => 'result',
			'merchantReference' => 'order_id',
			'merchantReturnData' => 'return_data',
			'pspReference' => 'gateway_txn_id',
			'skinCode' => 'skin_code',
		);
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
			'alt_locations' => array ( 'request' => 'merchantReference' ),
			'generate' => TRUE,
		);
	}

	function setGatewayDefaults() {}

	/**
	 * Define transactions
	 */
	function defineTransactions() {
		
		$this->transactions = array( );

		$this->transactions[ 'donate' ] = array(
			'request' => array(
				'allowedMethods',
				'billingAddress.street',
				'billingAddress.city',
				'billingAddress.postalCode',
				'billingAddress.stateOrProvince',
				'billingAddress.country',
				'billingAddressType',
				'card.cardHolderName',
				'currencyCode',
				'merchantAccount',
				'merchantReference',
				'merchantSig',
				'offset',
				'paymentAmount',
				'sessionValidity',
				'shipBeforeDate',
				'skinCode',
				'shopperLocale',
				'shopperEmail',
				// TODO more fields we might want to send to Adyen
				//'shopperReference',
				//'recurringContract',
				//'blockedMethods',
				//'shopperStatement',
				//'merchantReturnData',
				//'deliveryAddressType',
			),
			'values' => array(
				'allowedMethods' => implode( ',', $this->getAllowedPaymentMethods() ),
				'billingAddressType' => 2, // hide billing UI fields
				'merchantAccount' => $this->accountInfo[ 'merchantAccount' ],
				'sessionValidity' => date( 'c', strtotime( '+2 days' ) ),
				'shipBeforeDate' => date( 'Y-M-d', strtotime( '+2 days' ) ),
				'skinCode' => $this->accountInfo[ 'skinCode' ],
				//'shopperLocale' => language _ country
			),
			'iframe' => TRUE,
		);
	}

	public function definePaymentMethods() {
		$this->payment_methods = array(
			'cc' => array(
				'label' => 'Credit Cards',
				'validation' => array(
					'name' => true,
					'email' => true,
				),
			),
		);

		$card_types = array( 'visa', 'amex', 'mc', 'discover' );
		$this->payment_submethods = array();
		foreach( $card_types as $name ) {

			$this->payment_submethods[$name] = array(
				'countries' => array( 'US' => true ),
				'group' => 'cc',
				'validation' => array(
					'name' => true,
					'email' => true,
					'address' => true,
					'amount' => true,
				),
				'logo' => "card-{$name}.png",
			);
		}
	}

	protected function getAllowedPaymentMethods() {
		return array(
			'card',
		);
	}

	function doPayment() {
		return PaymentResult::fromResults(
			$this->do_transaction( 'donate' ),
			$this->getFinalStatus()
		);
	}

	/**
	 * FIXME: I can't help but feel like it's bad that the parent's do_transaction
	 * is never used at all.
	 */
	function do_transaction( $transaction ) {
		$this->session_addDonorData();
		$this->setCurrentTransaction( $transaction );
		$this->transaction_response = new PaymentTransactionResponse();

		if ( $this->transaction_option( 'iframe' ) ) {
			// slightly different than other gateways' iframe method,
			// we don't have to make the round-trip, instead just
			// stage the variables and return the iframe url in formaction.

			switch ( $transaction ) {
				case 'donate':
					$formaction = $this->url . '/hpp/pay.shtml';
					$this->runAntifraudHooks();
					$this->addRequestData( array ( 'risk_score' => $this->risk_score ) ); //this will also fire off staging again.
					if ( $this->getValidationAction() != 'process' ) {
						// copied from base class.
						$this->logger->info( "Failed pre-process checks for transaction type $transaction." );
						$message = $this->getErrorMapByCodeAndTranslate( 'internal-0000' );
						$this->transaction_response->setCommunicationStatus( false );
						$this->transaction_response->setMessage( $message );
						$this->transaction_response->setErrors( array(
							'internal-0000' => array(
								'message' => $message,
								'debugInfo' => "Failed pre-process checks for transaction type $transaction.",
								'logLevel' => LogLevel::INFO
							),
						) );
						break;
					}
					$this->stageData();
					$requestParams = $this->buildRequestParams();

					$this->transaction_response->setData( array(
						'FORMACTION' => $formaction,
						'gateway_params' => $requestParams,
					) );
					$this->logger->info( "launching external iframe request: " . print_r( $requestParams, true )
					);
					$this->setLimboMessage();
					break;
			}
		}
		return $this->transaction_response;
	}

	static function getCurrencies() {
		// See http://www.adyen.com/platform/all-countries-all-currencies/
		// This should be the list of all global "acceptance currencies".  Not
		// finding that list, I've used everything for which we keep
		// conversion rates.
		$currencies = array(
			'ADF', // Andorran Franc
			'ADP', // Andorran Peseta
			'AED', // Utd. Arab Emir. Dirham
			'AFA', // Afghanistan Afghani
			'AFN', // Afghanistan Afghani
			'ALL', // Albanian Lek
			'AMD', // Armenian Dram
			'ANG', // NL Antillian Guilder
			'AOA', // Angolan Kwanza
			'AON', // Angolan Old Kwanza
			'ARS', // Argentinian peso
			'ATS', // Austrian Schilling
			'AUD', // Australian Dollar
			'AWG', // Aruban Florin
			'AZM', // Azerbaijan Old Manat
			'AZN', // Azerbaijan New Manat
			'BAM', // Bosnian Mark
			'BBD', // Barbadian dollar
			'BDT', // Bangladeshi Taka
			'BEF', // Belgian Franc
			'BGL', // Bulgarian Old Lev
			'BGN', // Bulgarian Lev
			'BHD', // Bahraini Dinar
			'BIF', // Burundi Franc
			'BMD', // Bermudian Dollar
			'BND', // Brunei Dollar
			'BOB', // Bolivian Boliviano
			'BRL', // Brazilian Real
			'BSD', // Bahamian Dollar
			'BTN', // Bhutan Ngultrum
			'BWP', // Botswana Pula
			'BYR', // Belarusian Ruble
			'BZD', // Belize Dollar
			'CAD', // Canadian Dollar
			'CDF', // Congolese Franc
			'CHF', // Swiss Franc
			'CLP', // Chilean Peso
			'CNY', // Chinese Yuan Renminbi
			'COP', // Colombian Peso
			'CRC', // Costa Rican Colon
			'CUC', // Cuban Convertible Peso
			'CUP', // Cuban Peso
			'CVE', // Cape Verde Escudo
			'CYP', // Cyprus Pound
			'CZK', // Czech Koruna
			'DEM', // German Mark
			'DJF', // Djibouti Franc
			'DKK', // Danish Krone
			'DOP', // Dominican R. Peso
			'DZD', // Algerian Dinar
			'ECS', // Ecuador Sucre
			'EEK', // Estonian Kroon
			'EGP', // Egyptian Pound
			'ESP', // Spanish Peseta
			'ETB', // Ethiopian Birr
			'EUR', // Euro
			'FIM', // Finnish Markka
			'FJD', // Fiji Dollar
			'FKP', // Falkland Islands Pound
			'FRF', // French Franc
			'GBP', // British Pound
			'GEL', // Georgian Lari
			'GHC', // Ghanaian Cedi
			'GHS', // Ghanaian New Cedi
			'GIP', // Gibraltar Pound
			'GMD', // Gambian Dalasi
			'GNF', // Guinea Franc
			'GRD', // Greek Drachma
			'GTQ', // Guatemalan Quetzal
			'GYD', // Guyanese Dollar
			'HKD', // Hong Kong Dollar
			'HNL', // Honduran Lempira
			'HRK', // Croatian Kuna
			'HTG', // Haitian Gourde
			'HUF', // Hungarian Forint
			'IDR', // Indonesian Rupiah
			'IEP', // Irish Punt
			'ILS', // Israeli New Shekel
			'INR', // Indian Rupee
			'IQD', // Iraqi Dinar
			'IRR', // Iranian Rial
			'ISK', // Iceland Krona
			'ITL', // Italian Lira
			'JMD', // Jamaican Dollar
			'JOD', // Jordanian Dinar
			'JPY', // Japanese Yen
			'KES', // Kenyan Shilling
			'KGS', // Kyrgyzstanian Som
			'KHR', // Cambodian Riel
			'KMF', // Comoros Franc
			'KPW', // North Korean Won
			'KRW', // South Korean won
			'KWD', // Kuwaiti Dinar
			'KYD', // Cayman Islands Dollar
			'KZT', // Kazakhstani Tenge
			'LAK', // Lao Kip
			'LBP', // Lebanese Pound
			'LKR', // Sri Lankan Rupee
			'LRD', // Liberian Dollar
			'LSL', // Lesotho Loti
			'LTL', // Lithuanian Litas
			'LUF', // Luxembourg Franc
			'LVL', // Latvian Lats
			'LYD', // Libyan Dinar
			'MAD', // Moroccan Dirham
			'MDL', // Moldovan Leu
			'MGA', // Malagasy Ariary
			'MGF', // Malagasy Franc
			'MKD', // Macedonian Denar
			'MMK', // Myanmar Kyat
			'MNT', // Mongolian Tugrik
			'MOP', // Macau Pataca
			'MRO', // Mauritanian Ouguiya
			'MTL', // Maltese Lira
			'MUR', // Mauritius Rupee
			'MVR', // Maldive Rufiyaa
			'MWK', // Malawi Kwacha
			'MXN', // Mexican Peso
			'MYR', // Malaysian Ringgit
			'MZM', // Mozambique Metical
			'MZN', // Mozambique New Metical
			'NAD', // Namibia Dollar
			'NGN', // Nigerian Naira
			'NIO', // Nicaraguan Cordoba Oro
			'NLG', // Dutch Guilder
			'NOK', // Norwegian Kroner
			'NPR', // Nepalese Rupee
			'NZD', // New Zealand Dollar
			'OMR', // Omani Rial
			'PAB', // Panamanian Balboa
			'PEN', // Peruvian Nuevo Sol
			'PGK', // Papua New Guinea Kina
			'PHP', // Philippine Peso
			'PKR', // Pakistani Rupee
			'PLN', // Polish Złoty
			'PTE', // Portuguese Escudo
			'PYG', // Paraguay Guarani
			'QAR', // Qatari Rial
			'ROL', // Romanian Lei
			'RON', // Romanian New Lei
			'RSD', // Serbian Dinar
			'RUB', // Russian Rouble
			'RWF', // Rwandan Franc
			'SAR', // Saudi Riyal
			'SBD', // Solomon Islands Dollar
			'SCR', // Seychelles Rupee
			'SDD', // Sudanese Dinar
			'SDG', // Sudanese Pound
			'SDP', // Sudanese Old Pound
			'SEK', // Swedish Krona
			'SGD', // Singapore Dollar
			'SHP', // St. Helena Pound
			'SIT', // Slovenian Tolar
			'SKK', // Slovak Koruna
			'SLL', // Sierra Leone Leone
			'SOS', // Somali Shilling
			'SRD', // Suriname Dollar
			'SRG', // Suriname Guilder
			'STD', // Sao Tome/Principe Dobra
			'SVC', // El Salvador Colon
			'SYP', // Syrian Pound
			'SZL', // Swaziland Lilangeni
			'THB', // Thai Baht
			'TJS', // Tajikistani Somoni
			'TMM', // Turkmenistan Manat
			'TMT', // Turkmenistan New Manat
			'TND', // Tunisian Dinar
			'TOP', // Tonga Pa'anga
			'TRL', // Turkish Old Lira
			'TRY', // Turkish Lira
			'TTD', // Trinidad/Tobago Dollar
			'TWD', // New Taiwan dollar
			'TZS', // Tanzanian Shilling
			'UAH', // Ukrainian hryvnia
			'UGX', // Uganda Shilling
			'USD', // U.S. dollar
			'UYU', // Uruguayan Peso
			'UZS', // Uzbekistan Som
			'VEB', // Venezuelan Bolivar
			'VEF', // Venezuelan Bolivar Fuerte
			'VND', // Vietnamese Dong
			'VUV', // Vanuatu Vatu
			'WST', // Samoan Tala
			'XAF', // Central African CFA franc
			'XAG', // Silver (oz.)
			'XAU', // Gold (oz.)
			'XCD', // East Caribbean Dollar
			'XEU', // ECU
			'XOF', // West African CFA franc
			'XPD', // Palladium (oz.)
			'XPF', // CFP Franc
			'XPT', // Platinum (oz.)
			'YER', // Yemeni Rial
			'YUN', // Yugoslav Dinar
			'ZAR', // South African Rand
			'ZMK', // Zambian Kwacha
			'ZWD', // Zimbabwe Dollar
		);
		return $currencies;
	}

	//@TODO: Determine why this is being overloaded here.
	//This looks like a var-renamed copy of the parent. :[
	protected function buildRequestParams() {
		// Look up the request structure for our current transaction type in the transactions array
		$structure = $this->getTransactionRequestStructure();
		if ( !is_array( $structure ) ) {
			return FALSE;
		}

		$queryvals = array();
		foreach ( $structure as $fieldname ) {
			$fieldvalue = $this->getTransactionSpecificValue( $fieldname );
			if ( $fieldvalue !== '' && $fieldvalue !== false ) {
				$queryvals[$fieldname] = $fieldvalue;
			}
		}
		return $queryvals;
	}

	/**
	 * For Adyen, we only call this on the donor's return to the ResultSwitcher
	 * @param array $response GET/POST params from request
	 * @throws ResponseProcessingException
	 */
	public function processResponse( $response ) {
		// Always called outside do_transaction, so just make a new response object
		$this->transaction_response = new PaymentTransactionResponse();
		if ( empty( $response ) ) {
			$this->logger->info( "No response from gateway" );
			throw new ResponseProcessingException(
				'No response from gateway',
				ResponseCodes::NO_RESPONSE
			);
		}
		$this->logger->info( "Processing user return data: " . print_r( $response, TRUE ) );

		if ( !$this->checkResponseSignature( $response ) ) {
			$this->logger->info( "Bad signature in response" );
			throw new ResponseProcessingException(
				'Bad signature in response',
				ResponseCodes::BAD_SIGNATURE
			);
		}
		$this->logger->debug( 'Good signature' );

		$gateway_txn_id = isset( $response['pspReference'] ) ? $response['pspReference'] : '';

		$result_code = isset( $response['authResult'] ) ? $response['authResult'] : '';
		if ( $result_code == 'PENDING' || $result_code == 'AUTHORISED' ) {
			// Both of these are listed as pending because we have to submit a capture
			// request on 'AUTHORIZATION' ipn message receipt.
			$this->logger->info( "User came back as pending or authorised, placing in pending queue" );
			$this->finalizeInternalStatus( FinalStatus::PENDING );
		}
		else {
			$this->finalizeInternalStatus( FinalStatus::FAILED );
			$this->logger->info( "Negative response from gateway. Full response: " . print_r( $response, TRUE ) );
			throw new ResponseProcessingException(
				"Negative response from gateway. Full response: " . print_r( $response, TRUE ),
				ResponseCodes::UNKNOWN
			);
		}
		$this->transaction_response->setGatewayTransactionId( $gateway_txn_id );
		// FIXME: Why put that two places in transaction_response?
		$this->transaction_response->setTxnMessage( $this->getFinalStatus() );
		$this->runPostProcessHooks();
		$this->deleteLimboMessage();
	}

	/**
	 * TODO do we want to stage the country code for language variants?
	protected function stage_language( $type = 'request' ) {
	*/

	protected function stage_risk_score() {
		//This isn't smart enough to grab a new value here;
		//Late-arriving values have to trigger a restage via addData or
		//this will always equal the risk_score at the time of object
		//construction. Still need the formatting, though.
		if ( isset( $this->unstaged_data['risk_score'] ) ) {
			$this->staged_data['risk_score'] = ( string ) round( $this->unstaged_data['risk_score'] );
		}
	}

	protected function stage_hpp_signature() {
		$params = $this->buildRequestParams();
		if ( $params ) {
			$this->staged_data['hpp_signature'] = $this->calculateSignature( $params );
		}
	}

	/**
	 * Overriding @see GatewayAdapter::getTransactionSpecificValue to strip
	 * newlines.
	 * @param string $gateway_field_name
	 * @param boolean $token
	 * @return mixed
	 */
	protected function getTransactionSpecificValue( $gateway_field_name, $token = false ) {
		$value = parent::getTransactionSpecificValue( $gateway_field_name, $token );
		return str_replace( '\n', '', $value );
	}

	function checkResponseSignature( $requestVars ) {
		if ( !isset( $requestVars[ 'merchantSig' ] ) ) {
			return false;
		}

		$calculated_sig = $this->calculateSignature( $requestVars );
		return ( $calculated_sig === $requestVars[ 'merchantSig' ] );
	}

	protected function calculateSignature( $values ) {
		$ignoredKeys = array(
			'sig',
			'merchantSig',
			'title',
			'liberated',
		);

		foreach ( array_keys( $values ) as $key ) {
			if ( substr( $key, 0, 7 ) === 'ignore.' || in_array( $key, $ignoredKeys ) ) {
				unset( $values[$key] );
			} else {
				// escape colons and backslashes
				$values[$key] = str_replace( ':', '\\:', str_replace( '\\', '\\\\', $values[$key] ) );
			}
		}

		ksort( $values, SORT_STRING );

		$joined = implode( ':', array_merge( array_keys( $values ), array_values( $values ) ) );
		return base64_encode(
			hash_hmac( 'sha256', $joined, pack( "H*", $this->accountInfo[ 'hashSecret' ] ), true )
		);
	}
}
