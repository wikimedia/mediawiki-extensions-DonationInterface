<?php

class PayflowProGateway_Form_TwoStepTwoColumnPremium extends PayflowProGateway_Form_TwoStepTwoColumn {
	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoStepTwoColumnPremium.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}

	public function generateFormStart() {
		global $wgOut, $wgRequest, $wgScriptPath;

		$form = parent::generateBannerHeader();

		$form .= Xml::openElement( 'table', array( 'width' => '100%', 'cellspacing' => 0, 'cellpadding' => 0, 'border' => 0 ) );
		$form .= Xml::openElement( 'tr' );
		$form .= Xml::openElement( 'td', array( 'id' => 'appeal', 'valign' => 'top' ) );

		$form .= Xml::openElement( 'div', array( 'id' => 'premium-confirmation' ) );
		$form .= Xml::tags( 'div', array( 'id' => 'premium-header' ), wfMsg( 'payflowpro_gateway-tshirt-confirmation' ) );
		$form .= Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/Wikipedia-ten-tshirt-back.jpg", 'width' => '300', 'height' => '300' ) ) . "<br/>";
		$form .= Xml::openElement( 'div', array( 'id' => 'premium-values' ) );
		$form .= Xml::openElement( 'div', array( 'id' => 'premium-size' ) );
		$sizeDisplay = '<span id="size-display">'.$wgRequest->getText( 'size' ).'</span>';
		$form .= wfMsg( 'payflowpro_gateway-shirt-size-2', $sizeDisplay );
		$form .= Xml::closeElement( 'div' );  // close div#premium-size
		$form .= wfMsg( 'payflowpro_gateway-on-the-back' ) . "<br/>";
		$form .= Xml::openElement( 'div', array( 'id' => 'premium-language' ) );
		$form .= Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/wordmarks/".$wgRequest->getText( 'premium_language' )."-wordmark.png", 'width' => '200', 'height' => '92' ) );
		$form .= Xml::closeElement( 'div' );  // close div#premium-language
		$form .= Xml::closeElement( 'div' );  // close div#premium-values
		$form .= Xml::closeElement( 'div' );  // close div#premium-confirmation

		$form .= Xml::closeElement( 'td' );

		$form .= Xml::openElement( 'td', array( 'id' => 'donate', 'valign' => 'top' ) );

		// add noscript tags for javascript disabled browsers
		$form .= $this->getNoScript();

		$form .= Xml::tags( 'h2', array( 'id' => 'donate-head' ), wfMsg( 'payflowpro_gateway-please-complete' ) );

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
		$form .= $this->getStateField();
		// zip
		$form .= $this->getZipField();

		// country
		$form .= $this->getCountryField();

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
}
