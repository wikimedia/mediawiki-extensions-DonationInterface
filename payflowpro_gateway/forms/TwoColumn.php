<?php

class PayflowProGateway_Form_TwoColumn extends PayflowProGateway_Form {

	public function __construct( &$form_data, &$form_errors ) {
		parent::__construct( $form_data, $form_errors );
	}
	
	public function generateFormBody() {
		global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut;
		$form = $this->generateBannerHeader();
		
		$form .= $this->generatePersonalContainerTop();
		$form .= $this->generatePersonalFields();

		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'div' );
		
		$form .= $this->generatePaymentContainerTop();
		$form .= $this->generatePaymentFields();

		$form .= Xml::closeElement( 'table' ); 
		return $form;
	}

	public function generateFormSubmit() {
		global $wgScriptPath;
		// submit button and close form
		$form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit'));
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' )); 	
		//$form .= Xml::submitButton( wfMsg( 'payflowpro_gateway-submit-button' ));
		$form .= Xml::element( 'input', array( 'class' => 'input-button button-navyblue', 'value' => wfMsg( 'payflowpro_gateway-submit-button'), 'onclick' => 'submit_form( this )', 'type' => 'submit'));
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			wfMsg( 'payflowpro_gateway-donate-click' ); 
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		// add hidden fields			
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Xml::hidden( $field, $value );
		}
			
		$form .= Xml::closeElement( 'form' );

		$form .= Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-donate-addl-info' ));
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-donate-addl-info-secure-logos' ));
		
		$form .= Xml::tags( 'p', array( 'class' => '' ), Xml::openElement( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/rapidssl_ssl_certificate.gif" )));	
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-donate-addl-info-text' ));
		$form .= Xml::tags( 'p', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-otherways' ));
		$form .= Xml::tags( 'p', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-credit-storage-processing' ) );
		$form .= Xml::tags( 'p', array( 'class' => ''), wfMsg( 'payflowpro_gateway-question-comment' ) );
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );

		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		return $form;
	}

	protected function generateBannerHeader() {
		global $wgPayflowGatewayHeader, $wgOut;
		// intro text
		if ( $wgPayflowGatewayHeader ) {
			$header = str_replace( '@language', $this->form_data['language'], $wgPayflowGatewayHeader );
			$wgOut->addHtml( $wgOut->parse( $header ));
		}	
	}

	protected function generatePersonalContainerTop() {
		$form = Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) ); /*.
			Xml::openElement( 'div', array( 'id' => 'mw-creditcard-intro' ) ) .
			Xml::tags( 'p', array( 'class' => 'mw-creditcard-intro-msg' ), wfMsg( 'payflowpro_gateway-form-message' ) ) .
			Xml::closeElement( 'div' );*/
	
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

		// open form and table
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );
		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		$form .= Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-personal-info' ));			;
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-cc-form-header-personal' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );

		return $form;
	}

	protected function generatePersonalFields() {
		// first name			
		$form = '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-fname' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['fname'] . '</span></td>';
		$form .= "</tr>";

		/*// middle name
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-mname' ), 'mname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'mname', '30', $this->form_data['mname'], array( 'maxlength' => '15', 'id' => 'mname' ) ) . '</td>';
		$form .= '</tr>';
		*/

		// last name
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-lname' ), 'lname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'lname', '30', $this->form_data['lname'], array( 'maxlength' => '15', 'id' => 'lname' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['lname'] . '</span>' . '</td>';
		$form .= '</tr>';
			 
		// country
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) . '</td>';
		$form .= '<td>' . $this->generateCountryDropdown() . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['country'] . '</span></td>';
	    $form .= '</tr>';

		// street
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-street' ), 'street' ) . '</td>';
		$form .= '<td>' . Xml::input( 'street', '30', $this->form_data['street'], array( 'maxlength' => '30', 'id' => 'street' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['street'] . '</span></td>';
		$form .= '</tr>';


		// city
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-city' ), 'city' ) . '</td>';
		$form .= '<td>' . Xml::input( 'city', '30', $this->form_data['city'], array( 'maxlength' => '20', 'id' => 'city' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['city'] . '</span></td>';
		$form .= '</tr>';

		// state
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-state' ), 'state' ) . '</td>';
		$form .= '<td>' . $this->generateStateDropdown() . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['state'] . '</span></td>';
		$form .= '</tr>';
			
		// zip
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-postal' ), 'zip' ) . '</td>';
		$form .= '<td>' . Xml::input( 'zip', '30', $this->form_data['zip'], array( 'maxlength' => '9', 'id' => 'zip' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['zip'] . '</span></td>';
		$form .= '</tr>';
			
		// email
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-email' ), 'emailAdd' ) . '</td>';
		$form .= '<td>' . Xml::input( 'emailAdd', '30', $this->form_data['email'], array( 'maxlength' => '64', 'id' => 'emailAdd' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['emailAdd'] . '</span></td>';
		$form .= '</tr>';

		return $form;
	}

	protected function generatePaymentContainerTop() {
		// credit card info
		$form = Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-payment-info' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-payment' ), wfMsg( 'payflowpro_gateway-cc-form-header-payment' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc' ) );

		return $form;
	}

	protected function generatePaymentFields() {
		global $wgScriptPath, $wgPayflowGatewayTest;
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';

	// amount
		$form = '<tr>';
		$form .= '<td>' . Xml::label(wfMsg( 'payflowpro_gateway-amount-legend' ), 'amount', array( 'maxlength' => '10' ) ) . '</td>'; 
		$form .= '<td>' . Xml::input( 'amount', '7', $this->form_data['amount'], array( 'id' => 'amount' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['invalidamount'] . '</span>';
		$form .= $this->generateCurrencyDropdown() . '</td>';
		$form .= '</tr>';
		
		// card logos
		$form .= '<tr>';
		$form .= '<td />';
		$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" )) . '</td>';
		$form .= '</tr>';
		
		// credit card type
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-card' ), 'card' ) . '</td>';
		$form .= '<td>' . $this->generateCardDropdown() . '</td>';
		$form .= '</tr>';
		
		// card number
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-card-num' ), 'card_num' ) . '</td>';
		$form .= '<td>' . Xml::input( 'card_num', '30', $card_num, array( 'maxlength' => '100', 'id' => 'card_num', 'autocomplete' => 'off' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['card_num'] . '</span></td>';
		$form .= '</tr>';
		
		// expiry
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) . '</td>';
		$form .= '<td>' . $this->generateExpiryMonthDropdown() . $this->generateExpiryYearDropdown() . '</td>';
		$form .= '</tr>';
			
		// cvv
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-security' ), 'cvv' ) . '</td>';
		$form .= '<td>' . Xml::input( 'cvv', '5', $cvv, array( 'maxlength' => '10', 'id' => 'cvv', 'autocomplete' => 'off') ) .
			'<a href="javascript:PopupCVV();">' . wfMsg( 'word-separator' ) . wfMsg( 'payflowpro_gateway-cvv-link' ) . '</a>' .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['cvv'] . '</span></td>';
		$form .= '</tr>';

		return $form;
	}
}
