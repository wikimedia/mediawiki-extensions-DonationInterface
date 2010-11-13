<?php

abstract class PayflowProGateway_Form {

	/**
	 * Defines if we are in test mode
	 * @var bool
	 */
	public $test = false;

	/**
	 * An array of hidden fields, name => value
	 * @var array
	 */
	public $hidden_fields;

	/**
	 * An array of form data, passed from the payflow pro object
	 * @var array
	 */
	public $form_data;

	/**
	 * An array of form errors, passed from the payflow pro object
	 * @var array
	 */
	public $form_errors;

	/**
	 * The full path to CSS for the current form
	 * @var string
	 */
	protected $style_path;

	/**
	 * A string to hold the HTML to display a cpatcha
	 * @var string
	 */
	protected $captcha_html;

	/**
	 * Required method for returning the full HTML for a form.
	 *
	 * Code invoking forms will expect this method to be set.  Requiring only
	 * this method allows for flexible form generation inside of child classes
	 * while also providing a unified method for returning the full HTML for
	 * a form.
	 * @return string The entire form HTML
	 */
	abstract function getForm();

	public function __construct( &$data, &$error ) {
		global $wgPayflowGatewayTest, $wgOut;

		$this->test = $wgPayflowGatewayTest;
		$this->form_data =& $data;
		$this->form_errors =& $error;

		/**
		 *  add form-specific css - the path can be set in child classes
		 *  using $this->setStylePath, which should be called before
		 *  calling parent::__construct()
		 */
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath();
		}
		$wgOut->addExtensionStyle( $this->getStylePath() );
		/**
		 * if OWA is enabled, load the JS.  
		 * 
		 * We do this here (rather than in individual forms) because if OWA is 
		 * enabled, we ALWAYS want to make sure it gets included.
		 */
		if(defined('OWA')){
			$this->loadOwaJs();
		}
	}

	/**
	 * Set the path to the CSS file for the form
	 *
	 * This should be a full path, perhaps taking advantage of $wgScriptPath.
	 * If you do not pass the path to the method, the style path will default
	 * to the default css in css/Form.css
	 * @param string $style_path
	 */
	public function setStylePath( $style_path = null ) {
		global $wgScriptPath;
		if ( !$style_path ) {
			// load the default form CSS if the style path not explicitly set
			$style_path = $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/Form.css';
		}
		$this->style_path = $style_path;
	}

	/**
	 * Get the path to CSS
	 * @return String
	 */
	public function getStylePath() {
		return $this->style_path;
	}

	/**
	 * Generates the donation footer ("There are other ways to give...")
	 * @returns string of HTML
	 */
	public function generateDonationFooter() {
		global $wgScriptPath;
		$form = '';
		$form .= Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-donate-addl-info' ) );
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-donate-addl-info-secure-logos' ) );
		$form .= Xml::tags( 'p', array( 'class' => '' ), Xml::openElement( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/rapidssl_ssl_certificate-nonanimated.png" ) ) );
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-addl-info-secure-logos
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-donate-addl-info-text' ) );
		$form .= Xml::tags( 'p', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-otherways-short' ) );
		$form .= Xml::tags( 'p', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-credit-storage-processing' ) );
		$form .= Xml::tags( 'p', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-question-comment' ) );
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-addl-info-text
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-addl-info
		return $form;
	}

	/**
	 * Fetch the array of iso country codes => country names
	 * @return array
	 */
	public function getCountries() {
		require_once( dirname( __FILE__ ) . '/../includes/countryCodes.inc' );
		return countryCodes();
	}

	/**
	 * Generate the menu select of countries
	 * @fixme It would be great if we could default the country to the user's locale
	 * @fixme We should also do a locale-based asort on the country dropdown
	 * 	(see http://us.php.net/asort)
	 * @return string
	 */
	public function generateCountryDropdown() {
		$country_options = '';

		// create a new array of countries with potentially translated country names for alphabetizing later
		foreach ( $this->getCountries() as $iso_value => $full_name ) {
			$countries[ $iso_value ] = wfMsg( 'payflowpro_gateway-country-dropdown-' . $iso_value );
		}

		// alphabetically sort the country names
		asort( $countries, SORT_STRING );

		// generate a dropdown option for each country
		foreach ( $countries as $iso_value => $full_name ) {
			if ( $this->form_data[ 'country' ] ) {
				$selected = ( $iso_value == $this->form_data[ 'country' ] ) ? true : false;
			} else {
				$selected = ( $iso_value == 840 ) ? true : false; // Default to United States
			}
			$country_options .= Xml::option( $full_name, $iso_value, $selected );
		}

		// build the actual select
		$country_menu = Xml::openElement(
			'select',
			array(
				'name' => 'country',
				'id' => 'country',
				'onchange' => 'return disableStates( this )'
			) );
		$country_menu .= $country_options;
		$country_menu .= Xml::closeElement( 'select' );

		return $country_menu;
	}

	/**
	 * Genereat the menu select of credit cards
	 *
	 * @fixme Abstract out the setting of avaiable cards
	 * @return string
	 */
	public function generateCardDropdown() {
		$available_cards = array(
			'visa' => wfMsg( 'payflow_gateway-card-name-visa' ),
			'mastercard' => wfMsg( 'payflow_gateway-card-name-mc' ),
			'american' => wfMsg( 'payflow_gateway-card-name-amex' ),
			'discover' => wfMsg( 'payflow_gateway-card-name-discover' ),
		);

		$card_options = '';

		// generate  a dropdown opt for each card
		foreach ( $available_cards as $value => $card_name ) {
			// only load the card value if we're in testing mode
			$selected = ( $value == $this->form_data[ 'card' ] && $this->test ) ? true : false;
			$card_options .= Xml::option( $card_name, $value, $selected );
		}

		// build the actual select
		$card_menu = Xml::openElement(
			'select',
			array(
				'name' => 'card',
				'id' => 'card'
			) );
		$card_menu .= $card_options;
		$card_menu .= Xml::closeElement( 'select' );

		return $card_menu;
	}

	public function generateExpiryMonthDropdown() {
		global $wgLang;

		// derive the previously set expiry month, if set
		$month = NULL;
		if ( $this->form_data[ 'expiration' ] ) {
			$month = substr( $this->form_data[ 'expiration' ], 0, 2 );
		}

		$expiry_months = '';

		// generate a dropdown opt for each month
		for ( $i = 1; $i < 13; $i++ ) {
			$selected = ( $i == $month && $this->test ) ? true : false;
			$expiry_months .= Xml::option(
				wfMsg( 'payflowpro_gateway-month', $i, $wgLang->getMonthName( $i ) ),
				str_pad( $i, 2, '0', STR_PAD_LEFT ),
				$selected );
		}

		$expiry_month_menu = Xml::openElement(
			'select',
			array(
				'name' => 'mos',
				'id' => 'expiration'
			) );
		$expiry_month_menu .= $expiry_months;
		$expiry_month_menu .= Xml::closeElement( 'select' );
		return $expiry_month_menu;
	}

	public function generateExpiryYearDropdown() {
		// derive the previously set expiry year, if set
		$year = NULL;
		if ( $this->form_data[ 'expiration' ] ) {
			$year = substr( $this->form_data[ 'expiration' ], 2, 2 );
		}

		$expiry_years = '';

		// generate a dropdown of year opts
		for ( $i = 0; $i < 11; $i++ ) {
			$selected = ( date( 'Y' ) + $i == substr( date( 'Y' ), 0, 2 ) . $year
				&& $this->test ) ? true : false;
			$expiry_years .= Xml::option( date( 'Y' ) + $i, date( 'Y' ) + $i, $selected );
		}

		$expiry_year_menu = Xml::openElement(
			'select',
			array(
				'name' => 'year',
				'id' => 'year',
			) );
		$expiry_year_menu .= $expiry_years;
		$expiry_year_menu .= Xml::closeElement( 'select' );
		return $expiry_year_menu;
	}

	/**
	 * Generates the dropdown for states
	 * @fixme Alpha sort (ideally locale alpha sort) states in dropdown
	 * 	AFTER state names are translated
	 * @return string The entire HTML select element for the state dropdown list
	 */
	public function generateStateDropdown() {
		require_once( dirname( __FILE__ ) . '/../includes/stateAbbreviations.inc' );

		$states = statesMenuXML();

		$state_opts = '';

		// generate dropdown of state opts
		foreach ( $states as $value => $state_name ) {
			$selected = ( $this->form_data[ 'state' ] == $value ) ? true : false;
			$state_opts .= Xml::option( wfMsg( 'payflowpro_gateway-state-dropdown-' . $value ), $value, $selected );
		}

		$state_menu = Xml::openElement(
			'select',
			array(
				'name' => 'state',
				'id' => 'state'
			) );
		$state_menu .= $state_opts;
		$state_menu .= Xml::closeElement( 'select' );

		return $state_menu;
	}

	/**
	 * Generates the dropdown list for available currencies
	 *
	 * @fixme The list of available currencies should NOT be defined here but rather
	 * 	be customizable
	 * @fixme It would be great to default the currency to a locale's currency
	 * @return string The entire HTML select for the currency dropdown
	 */
	public function generateCurrencyDropdown() {
		$available_currencies = array(
			'USD' => 'USD: U.S. Dollar',
			'GBP' => 'GBP: British Pound',
			'EUR' => 'EUR: Euro',
			'AUD' => 'AUD: Australian Dollar',
			'CAD' => 'CAD: Canadian Dollar',
			'JPY' => 'JPY: Japanese Yen'
		);

		$currency_opts = '';

		// generate dropdown of currency opts
		foreach ( $available_currencies as $value => $currency_name ) {
			$selected = ( $this->form_data[ 'currency' ] == $value ) ? true : false;
			$currency_opts .= Xml::option( wfMsg( 'donate_interface-' . $value ), $value, $selected );
		}

		$currency_menu = Xml::openElement(
			'select',
			array(
				'name' => 'currency_code',
				'id' => 'input_currency_code'
			) );
		$currency_menu .= $currency_opts;
		$currency_menu .= Xml::closeElement( 'select' );

		return $currency_menu;
	}

	/**
	 * Set the hidden field array
	 *
	 * If you pass nothing in, we'll set the fields for you.
	 * @param array $hidden_fields
	 */
	public function setHiddenFields( $hidden_fields = NULL ) {
		if ( !$hidden_fields ) {
			$hidden_fields =  array(
				'utm_source' => $this->form_data[ 'utm_source' ],
				'utm_medium' => $this->form_data[ 'utm_medium' ],
				'utm_campaign' => $this->form_data[ 'utm_campaign' ],
		 		'language' => $this->form_data[ 'language' ],
				'referrer' => $this->form_data[ 'referrer' ],
				'comment' => $this->form_data[ 'comment' ],
				'comment-option' => $this->form_data[ 'comment-option' ],
				'email-opt' => $this->form_data[ 'email-opt' ],
				'process' => 'CreditCard',
				'payment_method' => 'processed',
				'token' => $this->form_data[ 'token' ],
				'orderid' => $this->form_data[ 'order_id' ],
				'numAttempt' => $this->form_data[ 'numAttempt' ],
				'contribution_tracking_id' => $this->form_data[ 'contribution_tracking_id' ],
				'data_hash' => $this->form_data[ 'data_hash' ],
				'action' => $this->form_data[ 'action' ],
				'owa_session' => $this->form_data[ 'owa_session' ],
				'owa_ref' => $this->form_data[ 'owa_ref' ],
			);
		}

		$this->hidden_fields = $hidden_fields;
	}

	/**
	 * Gets an array of the hidden fields for the form
	 *
	 * @return array
	 */
	public function getHiddenFields() {
		if ( !isset( $this->hidden_fields ) ) {
			$this->setHiddenFields();
		}
		return $this->hidden_fields;
	}

	/**
	 * Get the HTML set to display a captcha
	 *
	 * If $this->captcha_html has no string length, an empty string is returned.
	 * @return string The HTML to display the captcha or an empty string
	 */
	public function getCaptchaHTML() {
		if ( !strlen( $this->captcha_html ) ) {
			return '';
		}
		return $this->captcha_html;
	}

	/**
	 * Set a string of HTML used to display a captcha
	 *
	 * This allows for a flexible way of inserting some kind of captcha
	 * into a form, and for a form to flexibly insert captcha HTML
	 * wherever it needs to go.
	 *
	 * @param string The HTML to display the captcha
	 */
	public function setCaptchaHTML( $html ) {
		$this->captcha_html = $html;
	}

	protected function generateBannerHeader() {
		global $wgPayflowGatewayHeader, $wgOut, $wgRequest;

		$template = '';

		// intro text
		if ( $wgRequest->getText( 'masthead', false ) ) {
			$template = $wgOut->parse( '{{' . $wgRequest->getText( 'masthead' ) . '/' . $this->form_data[ 'language' ] . '}}' );
		} elseif ( $wgPayflowGatewayHeader ) {
			$header = str_replace( '@language', $this->form_data[ 'language' ], $wgPayflowGatewayHeader );
			$template = $wgOut->parse( $header );
		}

		// make sure that we actually have a matching template to display so we don't display the 'redlink'
		if ( strlen( $template ) && !preg_match( '/redlink\=1/', $template ) ) {
			$wgOut->addHtml( $template );
		}
	}

	protected function getEmailField() {
		// email
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['emailAdd'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-email' ), 'emailAdd' ) . '</td>';
		$form .= '<td>' . Xml::input( 'emailAdd', '30', $this->form_data['email'], array( 'type' => 'text', 'maxlength' => '64', 'id' => 'emailAdd', 'class' => 'fullwidth' ) ) .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getAmountField() {
		$otherChecked = false;
		$amount = -1;
		if ( $this->form_data['amount'] != 100 && $this->form_data['amount'] != 50 && $this->form_data['amount'] != 35 && $this->form_data['amount'] != 20 && $this->form_data['amountOther'] > 0 ) {
			$otherChecked = true;
			$amount = $this->form_data['amountOther'];
		}
		$form = '<tr>';
		$form .= '<td colspan="2"><span class="creditcard-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount' ) . '</td>';
		$form .= '<td>' . Xml::radio( 'amount', 100, $this->form_data['amount'] == 100 ) . '100 ' .
			Xml::radio( 'amount', 50, $this->form_data['amount'] == 50 ) . '50 ' .
			Xml::radio( 'amount', 35,  $this->form_data['amount'] == 35 ) . '35 ' .
			Xml::radio( 'amount', 20, $this->form_data['amount'] == 20 ) . '20 ' .
			'</td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label"></td>';
		$form .= '<td>' . Xml::radio( 'amount', $amount, $otherChecked, array( 'id' => 'otherRadio' ) ) . Xml::input( 'amountOther', '7', $this->form_data['amountOther'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-other' ).'\' )', 'onblur' => 'document.getElementById("otherRadio").value = this.value;if (this.value > 0) document.getElementById("otherRadio").checked=true;', 'maxlength' => '10', 'id' => 'amountOther' ) ) .
			' ' . $this->generateCurrencyDropdown() . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCardnumberField() {
		global $wgPayflowGatewayTest;
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$form = '';
		if ( $this->form_errors['card_num'] ) {
			$form .= '<tr>';
			$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['card_num'] . '</span></td>';
			$form .= '</tr>';
		}
		if ( $this->form_errors['card'] ) {
			$form .= '<tr>';
			$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['card'] . '</span></td>';
			$form .= '</tr>';
		}
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-card-num' ), 'card_num' ) . '</td>';
		$form .= '<td>' . Xml::input( 'card_num', '30', $card_num, array( 'type' => 'text', 'maxlength' => '100', 'id' => 'card_num', 'class' => 'fullwidth', 'autocomplete' => 'off' ) ) .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCvvField() {
		global $wgPayflowGatewayTest;
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';

		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['cvv'] . '</span></td>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-security' ), 'cvv' ) . '</td>';
		$form .= '<td>' . Xml::input( 'cvv', '5', $cvv, array( 'type' => 'text', 'maxlength' => '10', 'id' => 'cvv', 'autocomplete' => 'off' ) ) .
			' ' . '<a href="javascript:PopupCVV();">' . wfMsg( 'payflowpro_gateway-cvv-link' ) . '</a>' .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getStreetField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['street'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-street' ), 'street' ) . '</td>';
		$form .= '<td>' . Xml::input( 'street', '30', $this->form_data['street'], array( 'type' => 'text', 'maxlength' => '30', 'id' => 'street', 'class' => 'fullwidth' ) ) .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCityField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['city'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-city' ), 'city' ) . '</td>';
		$form .= '<td>' . Xml::input( 'city', '30', $this->form_data['city'], array( 'type' => 'text', 'maxlength' => '20', 'id' => 'city', 'class' => 'fullwidth' ) ) .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getZipField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['zip'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-postal' ), 'zip' ) . '</td>';
		$form .= '<td>' . Xml::input( 'zip', '30', $this->form_data['zip'], array( 'type' => 'text', 'maxlength' => '9', 'id' => 'zip', 'class' => 'fullwidth' ) ) .
			'</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getNameField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['fname'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['lname'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-name' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-first' ).'\' )', 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			Xml::input( 'lname', '30', $this->form_data['lname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-last' ).'\' )', 'maxlength' => '15', 'id' => 'lname' ) ) . '</td>';
		$form .= "</tr>";
		return $form;
	}

	protected function getCommentMessageField() {
		$form = '<tr>';
		$form .= '<td colspan="2">';
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ) );
		$form .= '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCommentField() {
		$form = '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-comment' ), 'comment' ) . '</td>';
		$form .= '<td>' . Xml::input( 'comment', '30', $this->form_data[ 'comment' ], array( 'type' => 'text', 'maxlength' => '200', 'class' => 'fullwidth' ) ) . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCommentOptionField() {
		global $wgRequest;
		$comment_opt_value = ( $wgRequest->wasPosted() ) ? $this->form_data[ 'comment-option' ] : true;
		$form = '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'comment-option', $comment_opt_value );
		$form .= ' ' . Xml::label( wfMsg( 'donate_interface-anon-message' ), 'comment-option' ) . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getEmailOptField() {
		global $wgRequest;
		$email_opt_value = ( $wgRequest->wasPosted() ) ? $this->form_data[ 'email-opt' ] : true;
		$form = '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'email-opt', $email_opt_value );
		$form .= ' ';
		// put the label inside Xml::openElement so any HTML in the msg might get rendered (right, Germany?)
		$form .= Xml::openElement( 'label', array( 'for' => 'email-opt' ) );
		$form .= wfMsg( 'donate_interface-email-agreement' );
		$form .= Xml::closeElement( 'label' );
		$form .= '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getPaypalButton() {
		global $wgScriptPath;
		$scriptPath = "$wgScriptPath/extensions/DonationInterface/payflowpro_gateway/includes";

		$form = '<tr>';
		$form .= '<td class="paypal-button" colspan="2">';
		$form .= Html::hidden( 'PaypalRedirect', false );
		$form .= Xml::tags( 'div',
				array(),
				'<a href="#" onclick="document.payment.PaypalRedirect.value=\'true\';document.payment.submit();"><img src="' . $scriptPath . '/donate_with_paypal.gif"/></a>'
			);
		$form .= '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getStateField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['state'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-state' ), 'state' ) . '</td>';
		$form .= '<td>' . $this->generateStateDropdown() . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getCountryField() {
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['country'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) . '</td>';
		$form .= '<td>' . $this->generateCountryDropdown() . '</td>';
	    $form .= '</tr>';
	    return $form;
	}

	protected function getCreditCardTypeField() {
		$form = '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-card' ), 'card' ) . '</td>';
		$form .= '<td>' . $this->generateCardDropdown() . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function getExpiryField() {
		$form = '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) . '</td>';
		$form .= '<td>' . $this->generateExpiryMonthDropdown() . $this->generateExpiryYearDropdown() . '</td>';
		$form .= '</tr>';
		return $form;
	}

	protected function loadValidateJs() {
		global $wgOut, $wgScriptPath;
		$wgOut->addHeadItem( 'validatescript', '<script type="text/javascript" src="' .
							$wgScriptPath .
							'/extensions/DonationInterface/payflowpro_gateway/validate_input.js?284"></script>' );
	}

	protected function loadApiJs() {
		global $wgOut, $wgScriptPath;
		$wgOut->addHeadItem( 'pfp_api_call', '<script type="text/javascript" src="' .
							$wgScriptPath .
							'/extensions/DonationInterface/payflowpro_gateway/pfp_api_controller.js?284"></script>' );
	}

	protected function loadOwaJs() {
		global $wgOut, $wgScriptPath;
		$wgOut->addHeadItem('owa_tracker_verts', '<script type="text/javascript" src="http://owa.tesla.usability.wikimedia.org/owa/modules/base/js/owa.tracker-combined-min.js"></script>');
		
		$wgOut->addHeadItem( 'owa_get_info', '<script type="text/javascript" src="' .
							$wgScriptPath .
							'/extensions/DonationInterface/payflowpro_gateway/owa_get_info.js?284"></script>' );
		$wgOut->addHeadItem( 'owa_tracker', '<script type="text/javascript" src="' .
							$wgScriptPath .
							'/extensions/DonationInterface/payflowpro_gateway/owa.tracker-combined-min.js?284"></script>' );
							
	}


	/**
	 * Generate HTML for <noscript> tags
	 *
	 * For displaying when a user does not have Javascript enabled in their browser.
	 */
	protected function getNoScript() {
		global $wgPayflowGatewayNoScriptRedirect;

		$form = '<noscript>';
		$form .= '<div id="noscript">';
		$form .= '<p id="noscript-msg">' . wfMsg( 'payflowpro_gateway-noscript-msg' ) . '</p>';
		if ( $wgPayflowGatewayNoScriptRedirect ) {
			$form .= '<p id="noscript-redirect-msg">' . wfMsg( 'payflowpro_gateway-noscript-redirect-msg' ) . '</p>';
			$form .= '<p id="noscript-redirect-link"><a href="' . $wgPayflowGatewayNoScriptRedirect . '">' . $wgPayflowGatewayNoScriptRedirect . '</a></p>';
		}
		$form .= '</div>';
		$form .= '</noscript>';
		return $form;
	}

	/**
	 * Determine the 'no cache' form action
	 *
	 * This mostly exists to ensure that the form does not try to use AJAX to
	 * overwrite certain hidden form params that are normally overwitten for
	 * cached versions of the form.
	 * @return string $url The full URL for the form to post to
	 */
	protected function getNoCacheAction() {
		global $wgRequest, $wgTitle;

		$url = $wgRequest->getFullRequestURL();
		$url_parts = wfParseUrl( $url );
		if ( isset( $url_parts[ 'query' ] ) ) {
			$query_array = wfCgiToArray( $url_parts[ 'query' ] );
		} else {
			$query_array = array();
		}

		// ensure that _cache_ does not get set in the URL
		unset( $query_array[ '_cache_' ] );

		// make sure no other data that might overwrite posted data makes it into the URL
		foreach ( $this->form_data as $key => $value ) {
			unset( $query_array[ $key ] );
		}

		// construct the submission url
		return wfAppendQuery( $wgTitle->getLocalURL(), $query_array );
	}
}
