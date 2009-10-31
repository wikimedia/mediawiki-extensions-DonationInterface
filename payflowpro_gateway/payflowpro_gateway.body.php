<?php

class PayflowProGateway extends UnlistedSpecialPage {

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'PayflowProGateway' );
		wfLoadExtensionMessages( 'PayflowProGateway' );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgScriptPath;

		$this->setHeaders();

		$wgOut->addHeadItem( 'validatescript', '<script type="text/javascript" language="javascript" src="' . $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/validate_input.js"></script>' );

		// create token if one doesn't already exist
		$token = $wgUser->editToken( 'mrxc877668DwQQ' );

		// Declare form post variables
		$data = array(
			'amount' => '',
			'email' => '',
			'fname' => '',
			'mname' => '',
			'lname' => '',
			'street' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => '',
			'card_num' => '',
			'expiration' => '',
			'cvv' => '',
			'currency' => '',
			'payment_method' => '',
			'order_id' => '',
			'numAttempt' => '',
			'referrer' => '',
			'utm_source' => '',
			'utm_medium' => '',
			'utm_campaign' => '',
			'language' => '',
			'comment' => '',
			'anonymous' => '',
			'optout' => '',
			'token' => $token,
		);

		$error[] = '';

		// find out if amount was a radio button or textbox, set amount
		if( isset( $_REQUEST['amount'] ) && preg_match( '/^\d+(\.(\d+)?)?$/', $wgRequest->getText( 'amount' ) ) ) {
			$amount = $wgRequest->getText( 'amount' );
		} elseif( isset( $_REQUEST['amount2'] ) && preg_match( '/^\d+(\.(\d+)?)?$/', $wgRequest->getText( 'amount2' ) ) ) { 
			$amount = number_format( $wgRequest->getText( 'amount2' ), 2, '.', '' );
		} else {
			$wgOut->addHTML( wfMsg( 'payflowpro_gateway-accessible' ) );
			return;
		}

		// track the number of attempts the user has made
		$numAttempt = ( $wgRequest->getText( 'numAttempt' ) == '' ) ? '0' : $wgRequest->getText( 'numAttempt' );
		// Populate from data
		$data = array(
			'amount' => $amount,
			'email' => $wgRequest->getText( 'emailAdd' ),
			'fname' => $wgRequest->getText( 'fname' ),
			'mname' => $wgRequest->getText( 'mname' ),
			'lname' => $wgRequest->getText( 'lname' ),
			'street' => $wgRequest->getText( 'street' ),
			'city' => $wgRequest->getText( 'city' ),
			'state' => $wgRequest->getText( 'state' ),
			'zip' => $wgRequest->getText( 'zip' ),
			'country' => $wgRequest->getText( 'country' ),
			'card' => $wgRequest->getText( 'card' ),
			'card_num' => str_replace( ' ', '', $wgRequest->getText( 'card_num' ) ),
			'expiration' => $wgRequest->getText( 'mos' ) . substr( $wgRequest->getText( 'year' ), 2, 2 ),
			'cvv' => $wgRequest->getText( 'cvv' ),
			'currency' => $wgRequest->getText( 'currency_code' ),
			'payment_method' => $wgRequest->getText( 'payment_method' ),
			'order-id' => null, //will be set with $payflow_data
			'numAttempt' => $numAttempt,
			'referrer' => $wgRequest->getText( 'referrer' ),
			'utm_source' => $wgRequest->getText( 'utm_source' ),
			'utm_medium' => $wgRequest->getText( 'utm_medium' ),
			'utm_campaign' => $wgRequest->getText( 'utm_campaign' ),
			'language' => $wgRequest->getText( 'language' ),
			'comment' => $wgRequest->getText( 'comment' ),
			'anonymous' => $wgRequest->getText( 'comment-option' ),
			'optout' => $wgRequest->getText( 'email' ),
			'test_string' => $wgRequest->getText( 'process' ), //for showing payflow string during testing
		);

		// Get array of default account values necessary for Payflow 
		require_once( 'includes/payflowUser.inc' );

		$payflow_data = payflowUser();

		// assign this order ID to the $data array as well
		$data['order_id'] = $payflow_data['order_id'];

		// Check form for errors and display 
		// match token
		$success = $wgUser->matchEditToken( $token, 'mrxc877668DwQQ' );

		if( $success ) {
			if( $data['payment_method'] == 'processed' ) {
				// Check form for errors and redisplay with messages
				$form_errors = $this->fnPayflowValidateForm( $data, $error );
				if( $form_errors ) {
					$this->fnPayflowDisplayForm( $data, $error );
				} else {
					// The submitted data is valid, so process it
					//increase the count of attempts
					++$data['numAttempt'];
					$this->fnPayflowProcessTransaction( $data, $payflow_data );
				}
			} else {
				//Display form for the first time
				$this->fnPayflowDisplayForm($data, $error);
			}
		}
	}

	/**
	 * Displays form to user
	 *
	 * @param $data Array: array of posted user input
	 * @param $error Array: array of error messages returned by validate_form function
	 *
	 * The message at the top of the form can be edited in the payflow_gateway.i18.php file
	 */
	private function fnPayflowDisplayForm( $data, &$error ) {
		require_once( 'includes/stateAbbreviations.inc' );
		require_once( 'includes/countryCodes.inc' );

		global $wgOut, $wgLang;

		$form = Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) ) .
			Xml::openElement( 'div', array( 'id' => 'mw-creditcard-intro' ) ) .
			Xml::tags( 'p', array( 'class' => 'mw-creditcard-intro-msg' ), wfMsg( 'payflowpro_gateway-form-message' ) ) .
			Xml::tags( 'p', array( 'class' => 'mw-creditcard-intro-msg' ), wfMsg( 'payflowpro_gateway-form-message-2' ) ) .
			Xml::closeElement( 'div' );

		// add hidden fields
		$form .= Xml::hidden( 'utm_source', $data['utm_source'] ) .
				Xml::hidden( 'utm_medium', $data['utm_medium'] ) .
				Xml::hidden( 'utm_campaign', $data['utm_campaign'] ) .
				Xml::hidden( 'language', $data['language'] ) .
				Xml::hidden( 'referrer', $data['referrer'] ) .
				Xml::hidden( 'comment', $data['comment'] ) .
				Xml::hidden( 'comment-option', $data['anonymous'] ) .
				Xml::hidden( 'email', $data['optout'] );

		// create drop down of countries
		$countries = countryCodes();

		foreach( $countries as $value => $fullName ) {
			$countryMenu .= Xml::option( $fullName, $value );
		}

		// Form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) ) . 
					Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)' ) ) .
					Xml::element( 'legend', array( 'class' => 'mw-creditcard-amount' ), wfMsg( 'payflowpro_gateway-amount-legend' ) . $data['amount'] ) .
					Xml::hidden( 'amount', $data['amount'] );

		$donorInput = array(
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-email' ), 'emailAdd', 'emailAdd', '30', $data['email'], array( 'maxlength' => '64' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['emailAdd'] . '</span>',
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-fname' ), 'fname', 'fname', '20', $data['fname'], array( 'maxlength' => '15', 'class' => 'required' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['fname'] . '</span>',
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-mname' ), 'mname', 'mname', '20', $data['mname'], array( 'maxlength' => '15' ) ),
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-lname' ), 'lname', 'lname', '20', $data['lname'], array( 'maxlength' => '15' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['lname'] . '</span>',
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-street' ), 'street', 'street', '30', $data['street'], array( 'maxlength' => '30' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['street'] . '</span>',
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-city' ), 'city', 'city', '20', $data['city'], array( 'maxlength' => '20' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['city'] . '</span>',
			Xml::label( wfMsg( 'payflowpro_gateway-donor-state' ), 'state' ) .
			Xml::openElement( 'select', array( 'name' => 'state', 'id' => 'state', 'value' => $data['state'] ) ) .
			statesMenuXML() .
			Xml::closeElement( 'select' ) . '<span class="creditcard_error_msg">' . '  ' . $error['state'] . '</span>',
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-postal' ), 'zip', 'zip', '15', $data['zip'], array( 'maxlength' => '9' ) ) . '<span class="creditcard_error_msg">' . '  ' . $error['zip'] . '</span>',
			Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) .
			Xml::openElement( 'select', array( 'name' => 'country', 'id' => 'country', 'value' => $data['country'] ) ) .
			$countryMenu .
			Xml::closeElement( 'select' )  . '<span class="creditcard_error_msg">' . '  ' . $error['country'] . '</span>'
		);

		$donorField = '';

		foreach( $donorInput as $value ) {
			$donorField .= '<p>' . $value . '</p>';
		}

		$form .= Xml::fieldset( wfMsg( 'payflowpro_gateway-donor-legend' ), $donorField, array( 'class' => 'mw-creditcard-donor' ) );

		$cardOptions = array(
			'visa' => 'Visa',
			'mastercard' => 'Mastercard',
			'american' => 'American Express',
		);

		foreach( $cardOptions as $value => $fullName ) {
			$cardOptionsMenu .= Xml::option( $fullName, $value );
		}

		$cardInput =
			Xml::label( wfMsg( 'payflowpro_gateway-donor-card' ), 'card' ) .
			Xml::openElement( 'select', array( 'name' => 'card', 'id' => 'card' ) ) .
			$cardOptionsMenu .
			Xml::closeElement( 'select' );

		$expMos = '';

		for( $i = 1; $i < 13; $i++ ) {
			$expMos .= Xml::option( $wgLang->getMonthName( $i ), str_pad( $i, 2, '0', STR_PAD_LEFT ) );
		}

		$expMosMenu =
			Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) .
			Xml::openElement( 'select', array( 'name' => 'mos', 'id' => 'mos' ) ) .
			$expMos .
			Xml::closeElement( 'select' );

		$expYr = '';
  
		for( $i = 0; $i < 11; $i++ ) {
			$expYr .= Xml::option( date( 'Y' ) + $i, date( 'Y' ) + $i );
		}

		$expYrMenu =
			Xml::openElement( 'select', array( 'name' => 'year', 'id' => 'year' ) ) .
			$expYr .
			Xml::closeElement( 'select' );

		$cardInput = array(
			Xml::label( wfMsg( 'payflowpro_gateway-donor-card' ), 'card' ) .
			Xml::openElement( 'select', array( 'name' => 'card', 'id' => 'card' ) ) .
			$cardOptionsMenu .
			Xml::closeElement( 'select'),
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-card-num' ), 'card_num', 'card_num', '30', '', array( 'maxlength' => '100' ) ) .
			'<span class="creditcard_error_msg">' . '  ' . $error['card_num'] . '</span>' .
			'<span class="creditcard_error_msg">' . '  ' . $error['card'] . '</span>',
			$expMosMenu . $expYrMenu,
			Xml::inputLabel( wfMsg( 'payflowpro_gateway-donor-security' ), 'cvv', 'cvv', '5', '', array( 'maxlength' => '10' ) ) .
			'<span class="creditcard_error_msg">' . '  ' . $error['cvv'] . '</span>',
		);

		foreach( $cardInput as $value ) {
			$cardField .= '<p>' . $value . '</p>';
		} 

		$form .= Xml::fieldset( wfMsg( 'payflowpro_gateway-card-legend' ), $cardField, array( 'class' => 'mw-creditcard-card' ) ) .
				Xml::hidden( 'process', 'CreditCard' ) .
				Xml::hidden( 'payment_method', 'processed' ) .
				Xml::hidden( 'token', $data['token'] ) .
				Xml::hidden( 'currency_code', $data['currency'] ) .
				Xml::hidden( 'orderid', $data['order_id'] ) .
				Xml::hidden( 'numAttempt', $data['numAttempt'] ) .
				Xml::submitButton( wfMsg( 'payflowpro_gateway-submit-button' ) ) .
				Xml::closeElement( 'form' ) .
				Xml::closeElement( 'div' );

		$form .= Xml::closeElement( 'div' ) . 
					Xml::Element( 'p', array( 'class' => 'mw-creditcard-submessage' ),
						wfMsg( 'payflowpro_gateway-donor-currency-msg', $data['currency'] )
					);
		$wgOut->addHTML( $form );
	}

	/**
	 * Checks posted form data for errors and returns array of messages
	 */
	private function fnPayflowValidateForm( $data, &$error ) {
		global $wgOut;

		// begin with no errors
		$error_result = '0';

		// create the human-speak message for required fields
		// does not include fields that are not required
		$msg = array(  
			'amount' => 'donation amount',
			'emailAdd' => 'email address',
			'fname' => 'first name',
			'lname' => 'last name',
			'street' => 'street address',
			'city' => 'city',
			'state' => 'state',
			'zip' => 'zip code',
			'card_num' => 'credit card number',
			'expiration' => "card's expiration date",
			'cvv' => 'the CVV from the back of your card',
		);

		// find all empty fields and create message  
		foreach( $data as $key => $value ) {
			if( $value == '' ) {
				// ignore fields that are not required
				if( $msg[$key] ) {
					$error[$key] = "**" . wfMsg( 'payflowpro_gateway-error-msg', $msg[$key] ) . "**<br />";
					$error_result = '1';
				}
			}
		}

		// is email address valid?
		$isEmail = User::isValidEmailAddr( $data['email'] );

		// create error message (supercedes empty field message)
		if( !$isEmail ) {
			$error['emailAdd'] = wfMsg( 'payflowpro_gateway-error-msg-email' );
			$error_result = '1';
		}

		// validate that credit card number entered is correct for the brand
		switch( $data['card'] ) {
			case 'american':
				// pattern for Amex
				$pattern = "/^3[47][0-9]{13}$/";

				// if the pattern doesn't match
				if( !preg_match( $pattern, $data['card_num'] ) ) {
					$error_result = '1';
					$error['card'] = wfMsg( 'payflowpro_gateway-error-msg-amex' );
				}

				break;

			case 'mastercard':
				// pattern for Mastercard
				$pattern = "/^5[1-5][0-9]{14}$/";

				// if pattern doesn't match
				if( !preg_match( $pattern, $data['card_num'] ) ) {
					$error_result = '1';
					$error['card'] = wfMsg( 'payflowpro_gateway-error-msg-mc' );
				}

				break;

			case 'visa':
				// pattern for Visa
				$pattern = "/^4[0-9]{12}(?:[0-9]{3})?$/";

				// if pattern doesn't match
				if( !preg_match( $pattern, $data['card_num'] ) ) {
					$error_result = '1';
					$error['card'] = wfMsg( 'payflowpro_gateway-error-msg-visa' );
				}

				break;
		} // end switch

		return $error_result;
	}

	/**
	 * Sends a name-value pair string to Payflow gateway
	 *
	 * @param $data Array: array of user input
	 * @param $payflow_data Array: array of necessary Payflow variables to
	 * 						include in string (i.e. Vendor, password)
	 */
	private function fnPayflowProcessTransaction( $data, $payflow_data ) {
		global $wgOut;

		// create payflow query string, include string lengths
		$queryArray = array(
			'TRXTYPE' => $payflow_data[trxtype],
			'TENDER'  => $payflow_data[tender],
			'USER'  => $payflow_data[user],
			'VENDOR' => $payflow_data[vendor],
			'PARTNER' => $payflow_data[partner],
			'PWD' => $payflow_data[password],
			'ACCT'  => $data[card_num],
			'EXPDATE' => $data[expiration],
			'AMT' => $data[amount],
			'FIRSTNAME' => $data[fname],
			'LASTNAME' => $data[lname],
			'STREET' => $data[street],
			'ZIP' => $data[zip],
			'INVNUM' => $payflow_data[order_id],
			'CVV2' => $data[cvv],
			'CURRENCY' => $data[currency],
			'VERBOSITY' => $payflow_data[verbosity],
			'CUSTIP' => $payflow_data[user_ip],
		);

		foreach( $queryArray as $name => $value ) {
			$query[] = $name . '[' . strlen( $value ) . ']=' . $value;
		}

		$queryString = implode( '&', $query );

		/* FOR TESTING: This is what the NV pair looks like, with string length included
		$payflow_query = "TRXTYPE=$payflow_data[trxtype]&TENDER=$payflow_data[tender]&USER=$payflow_data[user]&VENDOR=$payflow_data[vendor]&PARTNER=$payflow_data[partner]&PWD=$payflow_data[password]&ACCT=$data[card_num]&EXPDATE=$data[expiration]&AMT=$data[amount]&FIRSTNAME=$data[fname]&LASTNAME=$data[lname]&STREET=$data[street]&ZIP=$data[zip]&INVNUM=$payflow_data[order_id]&CVV2=$data[cvv]&CURRENCY=$data[currency]&VERBOSITY=$payflow_data[verbosity]&CUSTIP=$payflow_data[user_ip]"; 
		*/

		$payflow_query = $queryString;

		// assign header data necessary for the curl_setopt() function
		$user_agent = Http::userAgent();
		$headers[] = 'Content-Type: text/namevalue';
		$headers[] = 'Content-Length : ' . strlen( $payflow_query );
		$headers[] = 'X-VPS-Client-Timeout: 45';
		$headers[] = 'X-VPS-Request-ID:' . $payflow_data['order_id'];

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $payflow_data['testingurl'] );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_USERAGENT, $user_agent );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 90 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $payflow_query );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST,  2 );
		curl_setopt( $ch, CURLOPT_FORBID_REUSE, true ); 
		curl_setopt( $ch, CURLOPT_POST, 1 );

		// As suggested in the PayPal developer forum sample code, try more than once to get a response
		// in case there is a general network issue 
		$i = 1;

		while( $i++ <= 3 ) {
			$result = curl_exec( $ch );
			$headers = curl_getinfo( $ch );

			if( $headers['http_code'] != 200 && $headers['http_code'] != 403 ) {
				sleep( 5 );
			} elseif( $headers['http_code'] == 200 || $headers['http_code'] == 403 ) {
				break;
			}
		}

		if( $headers['http_code'] != 200 ) {
			$wgOut->addHTML( '<h3>No response from credit card processor.  Please try again later!</h3><p>' );
			$when = time();
			wfDebugLog( 'payflowpro_gateway', 'No response from credit card processor ' . $when );
			curl_close( $ch );
			exit;
		}

		curl_close( $ch );

		// get result string
		$result = strstr( $result, 'RESULT' );

		// parse string and display results to the user
		$this->fnPayflowGetResults( $data, $result );
	}

	/**
	 * "Reads" the name-value pair result string returned by Payflow and creates corresponding error messages
	 *
	 * @param $data Array: array of user input
	 * @param $result String: name-value pair results returned by Payflow
	 *
	 * Credit: code modified from payflowpro_example_EC.php posted (and supervised) on the PayPal developers message board
	 */
	private function fnPayflowGetResults( $data, $result ) {
		global $wgOut;

		// prepare NVP response for sorting and outputting 
		$responseArray = array();

		while( strlen( $result ) ) {
			// name
			$namepos = strpos( $result, '=' );
			$nameval = substr( $result, 0, $namepos );
			// value
			$valuepos = strpos( $result, '&' ) ? strpos( $result, '&' ) : strlen( $result );
			$valueval = substr( $result, $namepos + 1, $valuepos - $namepos - 1 );
			// decoding the respose
			$responseArray[$nameval] = $valueval;
			$result = substr( $result, $valuepos + 1, strlen( $result ) );
		}

		// errors fall into three categories, "try again please", "sorry it didn't work out", and "approved"
		// get the result code for response array
		$resultCode = $responseArray['RESULT'];

		// initialize response message
		$tryAgainResponse = '';
		$responseMsg = '';

		// interpret result code, return
		// approved (1), denied (2), try again (3), general error (4)
		$errorCode = $this->fnPayflowGetResponseMsg( $resultCode, $responseMsg );

		// if approved, display results and send transaction to the queue
		if( $errorCode == '1' ) {
			$this->fnPayflowDisplayApprovedResults( $data, $responseArray, $responseMsg );
			// give user a second chance to enter incorrect data
		} elseif( ( $errorCode == '3' ) && ( $data['numAttempt'] < '2' ) ) {
			// pass responseMsg as an array key as required by displayForm
			$tryAgainResponse[$responseMsg] = $responseMsg;
			$this->fnPayflowDisplayForm( $data, $tryAgainResponse );
			// if declined or if user has already made two attempts, decline
		} elseif( ( $errorCode == '2' ) || ( $data['numAttempt'] >= '2' ) ) {
			$this->fnPayflowDisplayDeclinedResults( $responseMsg );
		} elseif( ( $errorCode == '4' ) ) {
			$this->fnPayflowDisplayOtherResults( $responseMsg );
		}

	}// end display results

	/**
	 * Interpret response code, return 
	 * 1 if approved
	 * 2 if declined
	 * 3 if invalid data was submitted by user
	 * 4 all other errors
	 */
	function fnPayflowGetResponseMsg( $resultCode, &$responseMsg ) {
		$responseMsg = wfMsg( 'payflowpro_gateway-response-default' );
		$errorCode = '0';

		switch( $resultCode ) {
			case '0':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-0' );
				$errorCode = '1';
				break;
			case '126':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-126' );
				$errorCode = '1';
				break;
			case '12':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-12' );
				$errorCode = '2';
				break;
			case '13':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-13' );
				$errorCode = '2';
				break;
			case '114':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-114' );
				$errorCode = '2';
				break;
			case '4':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-4' );
				$errorCode = '3';
				break;
			case '23':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-23' );
				$errorCode = '3';
				break;
			case '24':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-24' );
				$errorCode = '3';
				break;
			case '112':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-112' );
				$errorCode = '3';
				break;
			case '125':
				$responseMsg = wfMsg( 'payflowpro_gateway-response-125' );
				$errorCode = '3';
				break;
			default:
				$responseMsg = wfMsg( 'payflowpro_gateway-response-default' );
				$errorCode = '4';
		}

		return $errorCode;
	} 

	/**
	 * Display response message to user with submitted user-supplied data
	 *
	 * @param $data Array: array of posted data from form
	 * @param $responseMsg String: message supplied by getResults function
	 */
	function fnPayflowDisplayApprovedResults( $data, $responseArray, $responseMsg ) {
		global $wgOut;
		$transaction = '';

		require_once( 'includes/countryCodes.inc' );

		// display response message
		$wgOut->addHTML( '<h3 class="response_message">' . $responseMsg . '</h3>' );

		// translate country code into text 
		$countries = countryCodes();

		$rows = array(
			'title' => array( wfMsg( 'payflowpro_gateway-post-transaction' ) ),
			'amount' => array( wfMsg( 'payflowpro_gateway-donor-amount' ), $data['amount'] ),
			'email' => array( wfMsg( 'payflowpro_gateway-donor-email' ), $data['email'] ),
			'name' => array( wfMsg( 'payflowpro_gateway-donor-name' ), $data['fname'], $data['mname'], $data['lname'] ),
			'address' => array( wfMsg( 'payflowpro_gateway-donor-address' ), $data['street'], $data['city'], $data['state'], $data['zip'], $countries[$data['country']] ),
		);

		// if we want to show the response
		$wgOut->addHTML( Xml::buildTable( $rows, array( 'class' => 'submitted-response' ) ) );

		// push to ActiveMQ server 
		// include response message
		$transaction['response'] = $responseMsg;
		// include date
		$transaction['date'] = time();
		// send both the country as text and the three digit ISO code
		$transaction['country_name'] = $countries[$data['country']];
		$transaction['country_code'] = $data['country'];
		// put all data into one array
		$transaction += array_merge( $data, $responseArray );

		// hook to call stomp functions
		wfRunHooks( 'gwStomp', array( &$transaction ) );
	}

	/**
	 * Display response message to user with submitted user-supplied data
	 *
	 * @param $responseMsg String: message supplied by getResults function
	 */
	function fnPayflowDisplayDeclinedResults( $responseMsg ) {
		global $wgOut;

		// general decline message
		$declinedDefault = wfMsg( 'php-response-declined' );

		// display response message
		$wgOut->addHTML( '<h3 class="response_message">' . $declinedDefault . $responseMsg . '</h3>' );
	}

	/**
	 * Display response message when there is a system error unrelated to user's entry
	 *
	 * @param $responseMsg String: message supplied by getResults function
	 */
	function fnPayflowDisplayOtherResults( $responseMsg ) {
		global $wgOut;

		// general decline message
		$declinedDefault = wfMsg( 'php-response-declined' );

		// display response message
		$wgOut->addHTML( '<h3 class="response_message">' . $declinedDefault . $responseMsg . '</h3>' );
	}

} // end class