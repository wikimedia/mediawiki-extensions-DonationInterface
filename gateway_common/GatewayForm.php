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
 * GatewayForm
 *
 */
class GatewayForm extends UnlistedSpecialPage {

	/**
	 * Defines the action to take on a transaction.
	 *
	 * Possible values include 'process', 'challenge',
	 * 'review', 'reject'.  These values can be set during
	 * data processing validation, for instance.
	 *
	 * Hooks are exposed to handle the different actions.
	 *
	 * Defaults to 'process'.
	 * @var string
	 */
	public $action = 'process';

	/**
	 * A container for the form class
	 *
	 * Used to loard the form object to display the CC form
	 * @var object
	 */
	public $form_class;

	/**
	 * An array of form errors
	 * @var array $errors
	 */
	public $errors = array( );

	/**
	 * The adapter object
	 * @var object $adapter
	 */
	public $adapter;

	/**
	 * The form is assumed to be successful. Errors in the form must set this to
	 * false.
	 *
	 * @var boolean
	 */
	public $validateFormResult = true;


	/**
	 * Constructor
	 */
	public function __construct() {
		$me = get_called_class();
		parent::__construct( $me );
		$this->errors = $this->getPossibleErrors();
		$this->setFormClass(); 
	}

	/**
	 * Checks posted form data for errors and returns array of messages
	 *
	 * This is update to GatewayForm::fnValidateForm().
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 * @param array	$options
	 *   OPTIONAL - You may require certain field groups to be validated
	 *   - address - Validates: street, city, state, zip 
	 *   - amount - Validates: amount
	 *   - creditCard - Validates: card_num, cvv, expiration and sets the card
	 *   - email - Validates: email
	 *   - name - Validates: fname, lname
	 *
	 * @return 0|1	Returns 0 on success and 1 on failure
	 *
	 * @see GatewayForm::fnValidateForm()
	 */
	public function validateForm( &$data, &$error, $options = array( ) ) {

		extract( $options );

		// Set which items will be validated
		$address = isset( $address ) ? ( boolean ) $address : true;
		$amount = isset( $amount ) ? ( boolean ) $amount : true;
		$creditCard = isset( $creditCard ) ? ( boolean ) $creditCard : false;
		$email = isset( $email ) ? ( boolean ) $email : true;
		$name = isset( $name ) ? ( boolean ) $name : true;

		// These are set in the order they will most likely appear on the form.

		if ( $name ) {
			$this->validateName( $data, $error );
		}

		if ( $address ) {
			$this->validateAddress( $data, $error );
		}

		if ( $amount ) {
			$this->validateAmount( $data, $error );
		}

		if ( $email ) {
			$this->validateEmail( $data, $error );
		}

		if ( $creditCard ) {
			$this->validateCreditCard( $data, $error );
		}

		/*
		 * $error_result would return 0 on success, 1 on failure.
		 *
		 * This is done for backward compatibility.
		 */
		return $this->getValidateFormResult() ? 0 : 1;
	}

	/**
	 * Validates the address
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateAddress( &$data, &$error ) {

		if ( empty( $data['street'] ) ) {

			$error['street'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-street' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['city'] ) ) {

			$error['city'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-city' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['state'] ) ) {

			$error['state'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-state' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['zip'] ) && $data['state'] != 'XX') {

			$error['zip'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-zip' );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Validates the amount contributed
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateAmount( &$data, &$error ) {

		if ( empty( $data['amount'] ) ) {

			$error['amount'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-amount' );

			$this->setValidateFormResult( false );
		}

		// check amount
		$priceFloor = $this->adapter->getGlobal( 'PriceFloor' );
		$priceCeiling = $this->adapter->getGlobal( 'PriceCeiling' );
		if ( !preg_match( '/^\d+(\.(\d+)?)?$/', $data['amount'] ) ||
			( ( float ) $this->convert_to_usd( $data['currency'], $data['amount'] ) < ( float ) $priceFloor ||
			( float ) $this->convert_to_usd( $data['currency'], $data['amount'] ) > ( float ) $priceCeiling ) ) {

			$error['invalidamount'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-invalid-amount' );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Validates a credit card
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateCreditCard( &$data, &$error ) {

		if ( empty( $data['card_num'] ) ) {

			$error['card_num'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-card_num' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['cvv'] ) ) {

			$error['cvv'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-cvv' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['expiration'] ) ) {

			$error['expiration'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-expiration' );

			$this->setValidateFormResult( false );
		}

		// validate that credit card number entered is correct and set the card type
		if ( preg_match( '/^3[47][0-9]{13}$/', $data['card_num'] ) ) { // american express
			$data['card'] = 'american';
		} elseif ( preg_match( '/^5[1-5][0-9]{14}$/', $data['card_num'] ) ) { //	mastercard
			$data['card'] = 'mastercard';
		} elseif ( preg_match( '/^4[0-9]{12}(?:[0-9]{3})?$/', $data['card_num'] ) ) {// visa
			$data['card'] = 'visa';
		} elseif ( preg_match( '/^6(?:011|5[0-9]{2})[0-9]{12}$/', $data['card_num'] ) ) { // discover
			$data['card'] = 'discover';
		} else { // an invalid credit card number was entered
			$error['card_num'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-card-num' );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Validates an email address.
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateEmail( &$data, &$error ) {

		if ( empty( $data['email'] ) ) {

			$error['email'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-email-empty' );

			$this->setValidateFormResult( false );
		}

		// is email address valid?
		$isEmail = User::isValidEmailAddr( $data['email'] );

		// create error message (supercedes empty field message)
		if ( !$isEmail ) {
			$error['email'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-email' );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Validates the name
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateName( &$data, &$error ) {

		if ( empty( $data['fname'] ) ) {

			$error['fname'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-fname' );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['lname'] ) ) {

			$error['lname'] = wfMsg( $this->adapter->getIdentifier() . '_gateway-error-msg-lname' );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Build and display form to user
	 *
	 * @param $data Array: array of posted user input
	 * @param $error Array: array of error messages returned by validate_form function
	 *
	 * The message at the top of the form can be edited in the payflow_gateway.i18n.php file
	 */
	public function displayForm( &$data, &$error ) {
		global $wgOut, $wgRequest;

		$form_class = $this->getFormClass();
		$form_obj = new $form_class( $data, $error, $this->adapter );
		$form = $form_obj->getForm();
		$wgOut->addHTML( $form );
	}

	/**
	 * Set the form class to use to generate the CC form
	 *
	 * @param string $class_name The class name of the form to use
	 */
	public function setFormClass( $class_name = NULL ) {
		if ( !$class_name ) {
			global $wgRequest;
			$defaultForm = $this->adapter->getGlobal( 'DefaultForm' );
			$form_class = $wgRequest->getText( 'form_name', $defaultForm );

			// make sure our form class exists before going on, if not try loading default form class
			$class_name = "Gateway_Form_" . $form_class;
			if ( !MWInit::classExists( $class_name ) ) {
				$class_name_orig = $class_name;
				$class_name = "Gateway_Form_" . $defaultForm;
				if ( !MWInit::classExists( $class_name ) ) {
					throw new MWException( 'Could not load form ' . $class_name_orig . ' nor default form ' . $class_name );
				}
			}
		}
		$this->form_class = $class_name;

		//this should... maybe replace the other thing? I need it in the adapter so reCaptcha can get to it. 
		$this->adapter->setFormClass( $class_name );
	}

	/**
	 * Get the currently set form class
	 *
	 * Will set the form class if the form class not already set
	 * Using logic in setFormClass()
	 * @return string
	 */
	public function getFormClass() {
		if ( !isset( $this->form_class ) ) {
			$this->setFormClass();
		}
		return $this->form_class;
	}

	function displayResultsForDebug( $results ) {
		global $wgOut;
		if ( $this->adapter->getGlobal( 'DisplayDebug' ) !== true ){
			return;
		}
		$wgOut->addHTML( HTML::element( 'span', null, $results['message'] ) );

		if ( !empty( $results['errors'] ) ) {
			$wgOut->addHTML( "<ul>" );
			foreach ( $results['errors'] as $code => $value ) {
				$wgOut->addHTML( HTML::element('li', null, "Error $code: $value" ) );
			}
			$wgOut->addHTML( "</ul>" );
		}

		if ( !empty( $results['data'] ) ) {
			$wgOut->addHTML( "<ul>" );
			foreach ( $results['data'] as $key => $value ) {
				if ( is_array( $value ) ) {
					$wgOut->addHTML( HTML::element('li', null, $key ) . '<ul>' );
					foreach ( $value as $key2 => $val2 ) {
						$wgOut->addHTML( HTML::element('li', null, "$key2: $val2" ) );
					}
					$wgOut->addHTML( "</ul>" );
				} else {
					$wgOut->addHTML( HTML::element('li', null, "$key: $value" ) );
				}
			}
			$wgOut->addHTML( "</ul>" );
		} else {
			$wgOut->addHTML( "Empty Results" );
		}
		if ( array_key_exists( 'Donor', $_SESSION ) ) {
			$wgOut->addHTML( "Session Donor Vars:<ul>" );
			foreach ( $_SESSION['Donor'] as $key => $val ) {
				$wgOut->addHTML( HTML::element('li', null, "$key: $val" ) );
			}
			$wgOut->addHTML( "</ul>" );
		} else {
			$wgOut->addHTML( "No Session Donor Vars:<ul>" );
		}

		if ( is_array( $this->adapter->debugarray ) ) {
			$wgOut->addHTML( "Debug Array:<ul>" );
			foreach ( $this->adapter->debugarray as $val ) {
				$wgOut->addHTML( HTML::element('li', null, $val ) );
			}
			$wgOut->addHTML( "</ul>" );
		} else {
			$wgOut->addHTML( "No Debug Array<ul>" );
		}
	}

	public function getPossibleErrors() {
		return array(
			'general' => '',
			'retryMsg' => '',
			'invalidamount' => '',
			'card_num' => '',
			'card_type' => '',
			'cvv' => '',
			'fname' => '',
			'lname' => '',
			'city' => '',
			'country' => '',
			'street' => '',
			'state' => '',
			'zip' => '',
			'emailAdd' => '',
		);
	}

	/**
	 * Convert an amount for a particular currency to an amount in USD
	 * 
	 * This is grosley rudimentary and likely wildly inaccurate.
	 * This mimicks the hard-coded values used by the WMF to convert currencies
	 * for validatoin on the front-end on the first step landing pages of their
	 * donation process - the idea being that we can get a close approximation
	 * of converted currencies to ensure that contributors are not going above
	 * or below the price ceiling/floor, even if they are using a non-US currency.
	 * 
	 * In reality, this probably ought to use some sort of webservice to get real-time
	 * conversion rates.
	 *  
	 * @param $currency_code
	 * @param $amount
	 * @return unknown_type
	 */
	public function convert_to_usd( $currency_code, $amount ) {
		switch ( strtoupper( $currency_code ) ) {
			case 'USD':
				$usd_amount = $amount / 1;
				break;
			case 'GBP':
				$usd_amount = $amount / 1;
				break;
			case 'EUR':
				$usd_amount = $amount / 1;
				break;
			case 'AUD':
				$usd_amount = $amount / 2;
				break;
			case 'CAD':
				$usd_amount = $amount / 1;
				break;
			case 'CHF':
				$usd_amount = $amount / 1;
				break;
			case 'CZK':
				$usd_amount = $amount / 20;
				break;
			case 'DKK':
				$usd_amount = $amount / 5;
				break;
			case 'HKD':
				$usd_amount = $amount / 10;
				break;
			case 'HUF':
				$usd_amount = $amount / 200;
				break;
			case 'JPY':
				$usd_amount = $amount / 100;
				break;
			case 'NZD':
				$usd_amount = $amount / 2;
				break;
			case 'NOK':
				$usd_amount = $amount / 10;
				break;
			case 'PLN':
				$usd_amount = $amount / 5;
				break;
			case 'SGD':
				$usd_amount = $amount / 2;
				break;
			case 'SEK':
				$usd_amount = $amount / 10;
				break;
			case 'ILS':
				$usd_amount = $amount / 5;
				break;
			default:
				$usd_amount = $amount;
				break;
		}

		return $usd_amount;
	}

	public function log( $msg, $log_level=LOG_INFO ) {
		$this->adapter->log( $msg, $log_level );
	}

	/**
	 * Handle redirection of form content to PayPal
	 *
	 * @fixme If we can update contrib tracking table in ContributionTracking
	 * 	extension, we can probably get rid of this method and just submit the form
	 *  directly to the paypal URL, and have all processing handled by ContributionTracking
	 *  This would make this a lot less hack-ish
	 */
	public function paypalRedirect() {
		global $wgOut;

		// if we don't have a URL enabled throw a graceful error to the user
		if ( !strlen( $this->adapter->getGlobal( 'PaypalURL' ) ) ) {
			$gateway_identifier = $this->adapter->getIdentifier();
			$this->errors['general']['nopaypal'] = wfMsg( $gateway_identifier . '_gateway-error-msg-nopaypal' );
			return;
		}
		// submit the data to the paypal redirect URL
		$wgOut->redirect( $this->adapter->getPaypalRedirectURL() );
	}

	/**
	 * Fetch the array of iso country codes => country names
	 * @return array
	 */
	public static function getCountries() {
		require_once( dirname( __FILE__ ) . '/../gateway_forms/includes/countryCodes.inc' );
		return countryCodes();
	}

	/**
	 * Get validate form result
	 *
	 * @return boolean
	 */
	public function getValidateFormResult() {

		return ( boolean ) $this->validateFormResult;
	}

	/**
	 * Set validate form result
	 *
	 * @param boolean $validateFormResult
	 */
	public function setValidateFormResult( $validateFormResult ) {

		$this->validateFormResult = empty( $validateFormResult ) ? false : ( boolean ) $validateFormResult;
	}

}

//end of GatewayForm class definiton
