<?php

class PayflowProGateway_Form_TwoStepTwoColumnLetterCA extends PayflowProGateway_Form_TwoStepTwoColumn {
	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoStepTwoColumnLetter.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}

	public function generateFormStart() {
		global $wgOut, $wgRequest;

		$form = parent::generateBannerHeader();

		$form .= Xml::openElement( 'table', array( 'width' => '100%', 'cellspacing' => 0, 'cellpadding' => 0, 'border' => 0 ) );
		$form .= Xml::openElement( 'tr' );
		$form .= Xml::openElement( 'td', array( 'id' => 'appeal', 'valign' => 'top' ) );

		$text_template = $wgRequest->getText( 'text_template', '2010/JimmyAppealLong' );
		// if the user has uselang set, honor that, otherwise default to the language set for the form defined by 'language' in the query string
		if ( $wgRequest->getText( 'language' ) ) $text_template .= '/' . $this->form_data[ 'language' ];

		$template = ( strlen( $text_template ) ) ? $wgOut->parse( '{{' . $text_template . '}}' ) : '';
		// if the template doesn't exist, prevent the display of the red link
		if ( preg_match( '/redlink\=1/', $template ) ) $template = NULL;
		$form .= $template;

		$form .= Xml::closeElement( 'td' );

		$form .= Xml::openElement( 'td', array( 'id' => 'donate', 'valign' => 'top' ) );

		// add noscript tags for javascript disabled browsers
		$form .= $this->getNoScript();

		$form .= Xml::tags( 'h2', array( 'id' => 'donate-head' ), wfMsg( 'payflowpro_gateway-make-your-donation' ) );

		// provide a place at the top of the form for displaying general messages
		if ( $this->form_errors['general'] ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'mw-payflow-general-error' ) );
			if ( is_array( $this->form_errors['general'] ) ) {
				foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
					$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
				}
			} else {
				$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
			}
			$form .= Xml::closeElement( 'div' );  // close div#mw-payflow-general-error
		}

		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => 'return formCheck(this)', 'autocomplete' => 'off' ) );

		$form .= $this->generateBillingContainer();
		return $form;
	}

	public function generateFormEnd() {
		$form = '';
		$form .= $this->generateFormClose();
		return $form;
	}

	protected function generateBillingContainer() {
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ) );
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		$form .= $this->generateBillingFields();
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generateBillingFields() {
		global $wgScriptPath;

		$form = '';

		// name
		$form .= $this->getNameField();

		// email
		$form .= $this->getEmailField();

		// amount
		$form .= '<tr>';
		$form .= '<td colspan="2"><span class="creditcard-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount' ) . '</td>';
		$form .= '<td>' . Xml::input( 'amount', '7', $this->form_data['amount'], array( 'type' => 'text', 'maxlength' => '10', 'id' => 'amount' ) ) .
			' ' . $this->generateCurrencyDropdown() . '</td>';
		$form .= '</tr>';

		// card logos
		if ( $this->form_data[ 'currency' ] == 'USD' ) {
			$form .= '<tr id="four_cards" style="display:table-row;">';
			$form .= '<td class="label"> </td><td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" ) ) . '</td>';
			$form .= '</tr>';
			$form .= '<tr id="two_cards" style="display:none;">';
			$form .= '<td class="label"> </td><td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos3.gif" ) ) . '</td>';
			$form .= '</tr>';
		} else {
			$form .= '<tr id="four_cards" style="display:none;">';
			$form .= '<td class="label"> </td><td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" ) ) . '</td>';
			$form .= '</tr>';
			$form .= '<tr id="two_cards" style="display:table-row;">';
			$form .= '<td class="label"> </td><td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos3.gif" ) ) . '</td>';
			$form .= '</tr>';
		}

		// card number
		$form .= $this->getCardNumberField();

		// cvv
		$form .= $this->getCvvField();

		// expiry
		$form .= $this->getExpiryField();

		// street
		$form .= $this->getStreetField();

		// city
		$form .= $this->getCityField();

		// state
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['state'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-state-province' ), 'state' ) . '</td>';
		$form .= '<td>' . $this->generateStateDropdown() . '</td>';
		$form .= '</tr>';
		
		// zip
		$form .= $this->getZipField();

		// country
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['country'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) . '</td>';
		$form .= '<td>' . $this->generateCountryDropdown( 124 ) . '</td>'; // Canada default
	    $form .= '</tr>';

		return $form;
	}

	/**
	 * Generate form closing elements
	 */
	public function generateFormClose() {
		$form = '';
		// add hidden fields
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Html::hidden( $field, $value );
		}

		$form .= Xml::closeElement( 'form' ); // close form 'payment'
		$form .= $this->generateDonationFooter();
		$form .= Xml::closeElement( 'td' );
		$form .= Xml::closeElement( 'tr' );
		$form .= Xml::closeElement( 'table' );
		return $form;
	}
	
	public function generateStateDropdown() {
		require_once( dirname( __FILE__ ) . '/../includes/provinceAbbreviations.inc' );

		$states = statesMenuXML();

		$state_opts = '';

		// generate dropdown of state opts
		foreach ( $states as $value => $state_name ) {
			$selected = ( $this->form_data[ 'state' ] == $value ) ? true : false;
			$state_opts .= Xml::option( $state_name, $value, $selected );
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
	
	public function generateCountryDropdown( $defaultCountry = 124 ) {
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
				$selected = ( $iso_value == $defaultCountry ) ? true : false; // Select default
			}
			$country_options .= Xml::option( $full_name, $iso_value, $selected );
		}

		// build the actual select
		$country_menu = Xml::openElement(
			'select',
			array(
				'name' => 'country',
				'id' => 'country'
			) );
		$country_menu .= $country_options;
		$country_menu .= Xml::closeElement( 'select' );

		return $country_menu;
	}
}
