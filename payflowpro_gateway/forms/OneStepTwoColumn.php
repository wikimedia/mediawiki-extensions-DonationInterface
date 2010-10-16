<?php

class PayflowProGateway_Form_OneStepTwoColumn extends PayflowProGateway_Form {
	public $paypal = false; // true for paypal only version

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;

		parent::__construct( $form_data, $form_errors );

		// update the list of hidden fields we need to use in this form.
		$this->updateHiddenFields();

		// we only want to load this JS if the form is being rendered
		$this->loadValidateJs(); // validation JS

		$this->loadApiJs(); // API/Ajax JS
	}

	/**
	* Required method for constructing the entire form
	*
	* This can of course be overloaded by a child class.
	* @return string The entire form HTML
	*/
	public function getForm() {
		$form = $this->generateFormStart();
		$form .= $this->getCaptchaHTML();
		$form .= $this->generateFormSubmit();
		$form .= $this->generateFormEnd();
		return $form;
	}

	public function generateFormStart() {
		global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut, $wgRequest;

		$this->paypal = $wgRequest->getBool( 'paypal', false );

		$form = $this->generateBannerHeader();

		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) );

		// provide a place at the top of the form for displaying general messages
		if ( $this->form_errors['general'] ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'mw-payflow-general-error' ));
			if ( is_array( $this->form_errors['general'] )) {
				foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
					$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
				}
			} else {
				$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
			}
			$form .= Xml::closeElement( 'div' );
		}

		// add noscript tags for javascript disabled browsers
		$form .= $this->getNoScript();

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );

		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );

		$form .= Xml::openElement( 'div', array( 'id' => 'left-column', 'class' => 'payflow-cc-form-section'));
		$form .= $this->generatePersonalContainer();

		if ( !$this->paypal ) {
			$form .= Xml::closeElement( 'div' ); // close div#left-column

			$form .= Xml::openElement( 'div', array( 'id' => 'right-column', 'class' => 'payflow-cc-form-section' ));
			$form .= $this->generatePaymentContainer();
		}

		return $form;
	}
	public function generateFormSubmit() {
		// submit button
		$form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit'));
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ));
		if ( $this->paypal ) {
			$form .= Xml::hidden( 'PaypalRedirect', false );
			$form .= Xml::element( 'input', array( 'class' => 'input-button button-navyblue', 'value' => wfMsg( 'payflowpro_gateway-submit-button'), 'onclick' => 'document.payment.PaypalRedirect.value=\'true\';document.payment.submit();', 'type' => 'submit'));
		} else {
			$form .= Xml::element( 'input', array( 'class' => 'input-button button-navyblue', 'value' => wfMsg( 'payflowpro_gateway-submit-button'), 'onclick' => 'submit_form( this )', 'type' => 'submit'));
			$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
			$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			wfMsg( 'payflowpro_gateway-donate-click' );
		}
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-submessage
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-form-submit

		return $form;
	}

	public function generateFormEnd() {
		$form = '';
		// add hidden fields
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Xml::hidden( $field, $value );
		}
		$form .= Xml::closeElement( 'div' ); // close div#right-column
		$form .= Xml::closeElement( 'form' );
		$form .= Xml::closeElement( 'div' ); // close div#mw-creditcard-form
		$form .= $this->generateDonationFooter();
		$form .= Xml::closeElement( 'div' ); // div#close mw-creditcard
		return $form;
	}

	protected function generateBannerHeader() {
		global $wgPayflowGatewayHeader, $wgOut, $wgRequest;
		$template = '';

		// intro text
		if ( $wgRequest->getText('masthead', false)) {
			$template = $wgOut->parse( '{{' . $wgRequest->getText( 'masthead' ) . '/' . $this->form_data[ 'language' ] . '}}' );
		} elseif ( $wgPayflowGatewayHeader ) {
			$header = str_replace( '@language', $this->form_data[ 'language' ], $wgPayflowGatewayHeader );
			$template = $wgOut->parse( $header );
		}

		// make sure that we actually have a matching template to display so we don't display the 'redlink'
		if ( strlen( $template ) && !preg_match( '/redlink\=1/', $template )) {
			$wgOut->addHtml( $template );
		}
	}

	protected function generatePersonalContainer() {
		global $wgRequest, $wgScriptPath;
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-make-your-donation' ));
		if ( !$this->paypal ) {
			$source = $wgRequest->getText( 'utm_source' );
			$medium = $wgRequest->getText( 'utm_medium' );
			$campaign = $wgRequest->getText( 'utm_campaign' );
			$formname = $wgRequest->getText( 'form_name' );
			$form .= Xml::Tags( 'p', array( 'id' => 'payflowpro_gateway-cc_otherways' ), wfMsg( 'payflowpro_gateway-paypal', $wgScriptPath, $formname, $source, $medium, $campaign ));
		}
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );

		$form .= $this->generatePersonalFields();

		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generatePersonalFields() {
		$form = '';

		// name
		$form .= $this->getNameField();

		// email
		$form .= $this->getEmailField();

		//comment message
		$form .= $this->getCommentMessageField();

		//comment
		$form .= $this->getCommentField();

		// anonymous
		$form .= $this->getCommentOptionField();

		// email agreement
		$form .= $this->getEmailOptField();

		// amount
		$form .= $this->getAmountField();

		return $form;
	}

	protected function generatePaymentContainer() {
		$form = '';
		// credit card info
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-payment-info' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc' ) );

		$form .= $this->generatePaymentFields();

		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-cc
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-payment-info

		return $form;
	}

	protected function generatePaymentFields() {
		global $wgScriptPath, $wgPayflowGatewayTest;

		$form = '';

		// card logos
		$form .= '<tr>';
		$form .= '<td />';
		$form .= '<td>&nbsp;<br/>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" )) . '</td>';
		$form .= '</tr>';

		// card number
		$form .= $this->getCardnumberField();

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
	* Update hidden fields to not set any comment-related fields
	*/
	public function updateHiddenFields() {
		$hidden_fields = $this->getHiddenFields();

		// make sure that the below elements are not set in the hidden fields
		$not_needed = array( 'comment-option', 'email-opt', 'comment' );

		foreach ( $not_needed as $field ) {
			unset( $hidden_fields[ $field ] );
		}

		$this->setHiddenFields( $hidden_fields );
	}
}
