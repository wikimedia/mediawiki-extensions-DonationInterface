<?php

class PayflowProAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Payflow Pro';
	const IDENTIFIER = 'payflowpro';
	public $communication_type = 'namevalue';
	const GLOBAL_PREFIX = 'wgPayflowProGateway';

	function defineAccountInfo() {
		$this->accountInfo = array(
			'PARTNER' => $this->account_config[ 'PartnerID' ], // PayPal or original authorized reseller
			'VENDOR' => $this->account_config[ 'VendorID' ], // paypal merchant login ID
			'USER' => $this->account_config[ 'UserID' ], // if one or more users are set up, authorized user ID, else same as VENDOR
			'PWD' => $this->account_config[ 'Password' ], // merchant login password
		);
	}

	/**
	 * Define dataConstraints
	 *
	 * @todo
	 * - Implement this for PayFlowPro
	 *
	 */
	public function defineDataConstraints() {
		
		$this->dataConstraints = array(
		);
	}
	
	/**
	 * Define error_map
	 *
	 * @todo
	 * - Add: Error messages
	 * - error_map is not used by PayflowProAdapter
	 */
	public function defineErrorMap() {
		
		$this->error_map = array(
			0		=> 'payflowpro_gateway-response-default',	
			
			// Internal messages
			'internal-0000' => 'donate_interface-processing-error', // Failed failed pre-process checks.
			'internal-0001' => 'donate_interface-processing-error', // Transaction could not be processed due to an internal error.
			'internal-0002' => 'donate_interface-processing-error', // Communication failure
		);
	}

	function defineVarMap() {
		$this->var_map = array(
			'ACCT' => 'card_num',
			'EXPDATE' => 'expiration',
			'AMT' => 'amount',
			'FIRSTNAME' => 'fname',
			'LASTNAME' => 'lname',
			'STREET' => 'street',
			'CITY' => 'city',
			'STATE' => 'state',
			'COUNTRY' => 'country',
			'ZIP' => 'zip',
			'INVNUM' => 'order_id',
			'CVV2' => 'cvv',
			'CURRENCY' => 'currency_code',
			'CUSTIP' => 'user_ip',
//			'ORDERID' => 'order_id',
//			'AMOUNT' => 'amount',
//			'CURRENCYCODE' => 'currency_code',
//			'LANGUAGECODE' => 'language',
//			'COUNTRYCODE' => 'country',
//			'MERCHANTREFERENCE' => 'order_id',
//			'RETURNURL' => 'returnto', //I think. It might not even BE here yet. Boo-urns. 
//			'IPADDRESS' => 'user_ip', //TODO: Not sure if this should be OUR ip, or the user's ip. Hurm.
		);
	}

	function defineReturnValueMap() {
		$this->return_value_map = array( ); //we don't really need this... maybe. 
	}

	function defineTransactions() {
		$this->transactions = array( );

		$this->transactions['Card'] = array(
			'request' => array(
				'TRXTYPE',
				'TENDER',
				'USER',
				'VENDOR',
				'PARTNER',
				'PWD',
				'ACCT',
				'EXPDATE',
				'AMT',
				'FIRSTNAME',
				'LASTNAME',
				'STREET',
				'CITY',
				'STATE',
				'COUNTRY',
				'ZIP',
				'INVNUM',
				'CVV2',
				'CURRENCY',
				'VERBOSITY',
				'CUSTIP',
			),
			'values' => array(
				'TRXTYPE' => 'S',
				'TENDER' => 'C',
				'VERBOSITY' => 'MEDIUM',
			),
		);
	}

	function definePaymentMethods() {
		$this->payment_methods = array(
			'cc' => array(),
		);
		PaymentMethod::registerMethods( $this->payment_methods );
	}

	/**
	 * Parse the response to get the status. Not sure if this should return a bool, or something more... telling.
	 */
	function getResponseStatus( $response ) {
		//this function is only supposed to make sure the communication was well-formed... 
		if ( is_array( $response ) && array_key_exists( 'RESULT', $response ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Interpret response code, return
	 * 1 if approved - 'complete'
	 * 2 if declined - 'failed'
	 * 3 if invalid data was submitted by user
	 * 4 all other errors
	 * 5 if pending - 'pending'
	 */
	function getResponseErrors( $response ) {
		if ( is_array( $response ) && array_key_exists( 'RESULT', $response ) ) {
			$resultCode = $response['RESULT'];
		} else {
			return;
		}

		$errors = array( );

		switch ( $resultCode ) {
			case '0':
				$errors['1'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-0' );
				$this->finalizeInternalStatus( 'complete' );
				break;
			case '126':
				$errors['5'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-126-2' );
				$this->finalizeInternalStatus( 'pending' );
				break;
			case '12':
				$errors['2'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-12' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '13':
				$errors['2'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-13' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '114':
				$errors['2'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-114' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '4':
				$errors['3'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-4' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '23':
				$errors['3'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-23' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '24':
				$errors['3'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-24' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '112':
				$errors['3'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-112' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			case '125':
				$errors['3'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-125-2' );
				$this->finalizeInternalStatus( 'failed' );
				break;
			default:
				$errors['4'] = WmfFramework::formatMessage( 'payflowpro_gateway-response-default' );
				$this->finalizeInternalStatus( 'failed' );
		}

		return $errors;
	}

	/**
	 * Harvest the data we need back from the gateway. 
	 * return a key/value array
	 */
	function getResponseData( $response ) {
		
		if ( is_array( $response ) && !empty( $response ) ) {
			return $response;
		}
	}
	
	/**
	 * Gets all the currency codes appropriate for this gateway
	 * @return array of currency codes
	 */
	static function getCurrencies() {
		$currencies = array(
			'USD', // U.S. Dollar
			'GBP', // British Pound
			'EUR', // Euro
			'AUD', // Australian Dollar
			'CAD', // Canadian Dollar
			'JPY', // Japanese Yen
		);
		return $currencies;
	}

	/**
	 * Perform any additional processing on the response obtained from the server.
	 *
	 * @param array $response   The internal response object array -> ie: data, errors, action...
	 * @param       $retryVars  If the transaction suffered a recoverable error, this will be
	 *  an array of all variables that need to be recreated and restaged.
	 */
	public function processResponse( $response, &$retryVars = null ) {
		//set the transaction result message
		if ( isset( $response['data']['RESPMSG'] ) ){
			$this->setTransactionResult( $response['data']['RESPMSG'], 'txn_message' );
		}
		if ( isset( $response['data']['PNREF'] ) ){
			$this->setTransactionResult( $response['data']['PNREF'], 'gateway_txn_id' );
		}
	}

	function defineStagedVars() {
		//OUR field names. 
		$this->staged_vars = array(
			'card_num',
		);
	}

	protected function stage_card_num( $type = 'request' ) {
		//I realize that the $type isn't used. Voodoo.
		$this->staged_data['card_num'] = str_replace( ' ', '', $this->staged_data['card_num'] );
	}
	
	protected function pre_process_card(){
		$this->runAntifraudHooks();
	}
	
	protected function post_process_card(){
		$this->runPostProcessHooks();
	}

}
