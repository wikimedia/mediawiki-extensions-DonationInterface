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
	}

	/**
	 * Checks posted form data for errors and returns array of messages
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
	 */
	public function validateForm( &$error, $options = array() ) {
		
		$data = $this->adapter->getData_Unstaged_Escaped();
		
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
	 * Required:
	 * - street
	 * - city
	 * - state
	 * - zip
	 * - country
	 *
	 * @param array	$data	Reference to the data of the form
	 * @param array	$error	Reference to the error messages of the form
	 *
	 * @see GatewayForm::validateForm()
	 */
	public function validateAddress( &$data, &$error ) {

		if ( empty( $data['street'] ) ) {

			$error['street'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-street' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['city'] ) ) {

			$error['city'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-city' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['state'] ) || $data['state'] == 'YY' ) {

			$error['state'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-state-province' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['country'] ) || !array_key_exists( $data['country'], $this->getCountries() )) {

			$error['country'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-country' ) );

			$this->setValidateFormResult( false );
		}

		$ignoreCountries = array();
		
		if ( empty( $data['zip'] ) && !in_array( $data['country'], $ignoreCountries ) ) {

			$error['zip'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-zip' ) );

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

			$error['amount'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-amount' ) );

			$this->setValidateFormResult( false );
		}

		// check amount
		$priceFloor = $this->adapter->getGlobal( 'PriceFloor' );
		$priceCeiling = $this->adapter->getGlobal( 'PriceCeiling' );
		if ( !preg_match( '/^\d+(\.(\d+)?)?$/', $data['amount'] ) ||
			( ( float ) $this->convert_to_usd( $data['currency_code'], $data['amount'] ) < ( float ) $priceFloor ||
			( float ) $this->convert_to_usd( $data['currency_code'], $data['amount'] ) > ( float ) $priceCeiling ) ) {

			$error['invalidamount'] = wfMsg( 'donate_interface-error-msg-invalid-amount' );

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

			$error['card_num'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-card_num' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['cvv'] ) ) {

			$error['cvv'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-cvv' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['expiration'] ) ) {

			$error['expiration'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-expiration' ) );

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
			$error['card_num'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-card-num' ) );

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

			$error['email'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-email-empty' ) );

			$this->setValidateFormResult( false );
		}

		// is email address valid?
		$isEmail = User::isValidEmailAddr( $data['email'] );

		// create error message (supercedes empty field message)
		if ( !$isEmail ) {
			$error['email'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-email' ) );

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

			$error['fname'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-fname' ) );

			$this->setValidateFormResult( false );
		}

		if ( empty( $data['lname'] ) ) {

			$error['lname'] = wfMsg( 'donate_interface-error-msg', wfMsg( 'donate_interface-error-msg-lname' ) );

			$this->setValidateFormResult( false );
		}
	}

	/**
	 * Build and display form to user
	 *
	 * @param $error Array: array of error messages returned by validate_form function
	 *
	 * The message at the top of the form can be edited in the payflow_gateway.i18n.php file
	 */
	public function displayForm( &$error ) {
		global $wgOut;

		$form_class = $this->getFormClass();
		if ( $form_class && class_exists( $form_class ) ){
			$form_obj = new $form_class( $this->adapter, $error );
			$form = $form_obj->getForm();
			$wgOut->addHTML( $form );
		} else {
			throw new MWException( 'No valid form to load.' );
		}
	}

	/**
	 * Get the currently set form class
	 * @return mixed string containing the valid and enabled form class, otherwise false. 
	 */
	public function getFormClass() {
		return $this->adapter->getFormClass();
	}

	/**
	 * displayResultsForDebug
	 *
	 * Displays useful information for debugging purposes. 
	 * Enable with $wgDonationInterfaceDisplayDebug, or the adapter equivalent.
	 * @return null
	 */
	protected function displayResultsForDebug( $results = array() ) {
		global $wgOut;
		
		$results = empty( $results ) ? $this->adapter->getTransactionAllResults() : $results;
		
		if ( $this->adapter->getGlobal( 'DisplayDebug' ) !== true ){
			return;
		}
		$wgOut->addHTML( HTML::element( 'span', null, $results['message'] ) );

		if ( !empty( $results['errors'] ) ) {
			$wgOut->addHTML( HTML::openElement( 'ul' ) );
			foreach ( $results['errors'] as $code => $value ) {
				$wgOut->addHTML( HTML::element('li', null, "Error $code: $value" ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		}

		if ( !empty( $results['data'] ) ) {
			$wgOut->addHTML( HTML::openElement( 'ul' ) );
			foreach ( $results['data'] as $key => $value ) {
				if ( is_array( $value ) ) {
					$wgOut->addHTML( HTML::openElement('li', null, $key ) . HTML::openElement( 'ul' ) );
					foreach ( $value as $key2 => $val2 ) {
						$wgOut->addHTML( HTML::element('li', null, "$key2: $val2" ) );
					}
					$wgOut->addHTML( HTML::closeElement( 'ul' ) . HTML::closeElement( 'li' ) );
				} else {
					$wgOut->addHTML( HTML::element('li', null, "$key: $value" ) );
				}
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "Empty Results" );
		}
		if ( array_key_exists( 'Donor', $_SESSION ) ) {
			$wgOut->addHTML( "Session Donor Vars:" . HTML::openElement( 'ul' ));
			foreach ( $_SESSION['Donor'] as $key => $val ) {
				$wgOut->addHTML( HTML::element('li', null, "$key: $val" ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "No Session Donor Vars:" );
		}

		if ( is_array( $this->adapter->debugarray ) ) {
			$wgOut->addHTML( "Debug Array:" . HTML::openElement( 'ul' ) );
			foreach ( $this->adapter->debugarray as $val ) {
				$wgOut->addHTML( HTML::element('li', null, $val ) );
			}
			$wgOut->addHTML( HTML::closeElement( 'ul' ) );
		} else {
			$wgOut->addHTML( "No Debug Array" );
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
	 * @param string $currency_code
	 * @param float $amount
	 * @return float
	 */
	static function convert_to_usd( $currency_code, $amount ) {
		require_once( dirname( __FILE__ ) . '/currencyRates.inc' );
		$rates = getCurrencyRates();
		$code = strtoupper( $currency_code );
		if ( array_key_exists( $code, $rates ) ) {
			$usd_amount = $amount / $rates[$code];
		} else {
			$usd_amount = $amount;
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

	/**
	 * Handle the result from the gateway
	 *
	 * If there are errors, then this will return to the form.
	 *
	 * @todo
	 * - This is being implemented in GlobalCollect
	 * - Do we need to implement this for PayFlow Pro? Not yet!
	 * - Do we only want to skip the Thank you page on getTransactionWMFStatus() => failed?
	 *
	 * @return null
	 */
	protected function resultHandler() {
		
		global $wgOut;

		// If transaction is anything, except failed, go to the thank you page.
		
		if ( in_array( $this->adapter->getTransactionWMFStatus(), $this->adapter->getGoToThankYouOn() ) ) {

			$thankyoupage = $this->adapter->getThankYouPage();
	
			if ( $thankyoupage ) {
				
				$queryString = '?payment_method=' . $this->adapter->getPaymentMethod() . '&payment_submethod=' . $this->adapter->getPaymentSubmethod();
				
				return $wgOut->redirect( $thankyoupage . $queryString );
			}
		}
		
		// If we did not go to the Thank you page, there must be an error.
		return $this->resultHandlerError();
	}

	/**
	 * Handle the error result from the gateway
	 *
	 * @todo
	 * - logging may need be added to this method
	 *
	 * @return null
	 */
	protected function resultHandlerError() {

		// Display debugging results
		$this->displayResultsForDebug();

		$this->errors['general'] = ( !isset( $this->errors['general'] ) || empty( $this->errors['general'] ) ) ? array() : (array) $this->errors['general'];

		$this->errors['retryMsg'] = ( !isset( $this->errors['retryMsg'] ) || empty( $this->errors['retryMsg'] ) ) ? array() : (array) $this->errors['retryMsg'];

		foreach ( $this->adapter->getTransactionErrors() as $code => $message ) {
			
			if ( strpos( $code, 'internal' ) === 0 ) {
				$this->errors['retryMsg'][ $code ] = $message;
			}
			else {
				$this->errors['general'][ $code ] = $message;
			}
		}
		
		return $this->displayForm( $this->errors );
	}

}

//end of GatewayForm class definiton
