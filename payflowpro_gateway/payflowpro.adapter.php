<?php

class PayflowProAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'Payflow Pro';
	const IDENTIFIER = 'payflowpro';
	const COMMUNICATION_TYPE = 'namevalue';
	const GLOBAL_PREFIX = 'wgPayflowProGateway';

	function defineAccountInfo() {
		$this->accountInfo = array(
			'PARTNER' => self::getGlobal( 'PartnerID' ), // PayPal or original authorized reseller
			'VENDOR' => self::getGlobal( 'VendorID' ), // paypal merchant login ID
			'USER' => self::getGlobal( 'UserID' ), // if one or more users are set up, authorized user ID, else same as VENDOR
			'PWD' => self::getGlobal( 'Password' ), // merchant login password
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
			'CURRENCY' => 'currency',
			'CUSTIP' => 'user_ip',
//			'ORDERID' => 'order_id',
//			'AMOUNT' => 'amount',
//			'CURRENCYCODE' => 'currency',
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
			'do_validation' => true,
			'do_processhooks' => true,
		);
	}

	/**
	 * Take the entire response string, and strip everything we don't care about.
	 * For instance: If it's XML, we only want correctly-formatted XML. Headers must be killed off. 
	 * return a string.
	 */
	function getFormattedResponse( $rawResponse ) {
		$nvString = $this->stripNameValueResponseHeaders( $rawResponse );

		// prepare NVP response for sorting and outputting
		$responseArray = array( );

		/**
		 * The result response string looks like:
		 * 	RESULT=7&PNREF=E79P2C651DC2&RESPMSG=Field format error&HOSTCODE=10747&DUPLICATE=1
		 * We want to turn this into an array of key value pairs, so explode on '&' and then
		 * split up the resulting strings into $key => $value
		 */
		$result_arr = explode( "&", $nvString );
		foreach ( $result_arr as $result_pair ) {
			list( $key, $value ) = preg_split( "/=/", $result_pair, 2 );
			$responseArray[$key] = $value;
		}

		self::log( "Here is the response as an array: " . print_r( $responseArray, true ) );
		return $responseArray;
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
				$errors['1'] = wfMsg( 'payflowpro_gateway-response-0' );
				$this->setTransactionWMFStatus( 'complete' );
				break;
			case '126':
				$errors['5'] = wfMsg( 'payflowpro_gateway-response-126-2' );
				$this->setTransactionWMFStatus( 'pending' );
				break;
			case '12':
				$errors['2'] = wfMsg( 'payflowpro_gateway-response-12' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '13':
				$errors['2'] = wfMsg( 'payflowpro_gateway-response-13' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '114':
				$errors['2'] = wfMsg( 'payflowpro_gateway-response-114' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '4':
				$errors['3'] = wfMsg( 'payflowpro_gateway-response-4' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '23':
				$errors['3'] = wfMsg( 'payflowpro_gateway-response-23' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '24':
				$errors['3'] = wfMsg( 'payflowpro_gateway-response-24' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '112':
				$errors['3'] = wfMsg( 'payflowpro_gateway-response-112' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			case '125':
				$errors['3'] = wfMsg( 'payflowpro_gateway-response-125-2' );
				$this->setTransactionWMFStatus( 'failed' );
				break;
			default:
				$errors['4'] = wfMsg( 'payflowpro_gateway-response-default' );
				$this->setTransactionWMFStatus( 'failed' );
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
	 * Actually do... stuff. Here. 
	 * TODO: Better comment. 
	 * Process the entire response gott'd by the last four functions. 
	 */
	function processResponse( $response ) {
		//set the transaction result message
		$this->setTransactionResult( $response['RESPMSG'], 'txn_message' );
		$this->setTransactionResult( $response['PNREF'], 'gateway_txn_id' );
	}

	function defineStagedVars() {
		//OUR field names. 
		$this->staged_vars = array(
			'card_num',
			'user_ip'
		);
	}

	protected function stage_card_num( $type = 'request' ) {
		//I realize that the $type isn't used. Voodoo.
		$this->postdata['card_num'] = str_replace( ' ', '', $this->postdata['card_num'] );
	}

	//TODO: Something much fancier here. 
	protected function stage_user_ip( $type = 'request' ) {
		if ( $this->postdata['user_ip'] === '127.0.0.1' ) {
			global $wgDonationInterfaceIPAddress;
			if ( !empty( $wgDonationInterfaceIPAddress ) ) {
				$this->postdata['user_ip'] = $wgDonationInterfaceIPAddress;
			}
		}
	}

}
