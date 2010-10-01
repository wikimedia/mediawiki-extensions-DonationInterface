<?php

class PayflowProGateway_Form_TwoColumn extends PayflowProGateway_Form {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		
		parent::__construct( $form_data, $form_errors );
		
		// we only want to load this JS if the form is being rendered
		$wgOut->addHeadItem( 'validatescript', '<script type="text/javascript" src="' . 
				     $wgScriptPath . 
 				     '/extensions/DonationInterface/payflowpro_gateway/validate_input.js"></script>' );
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
		global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut;
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

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );
		
		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		
		$form .= Xml::openElement( 'div', array( 'id' => 'left-column', 'class' => 'payflow-cc-form-section'));
		$form .= $this->generatePersonalContainer();
		$form .= Xml::closeElement( 'div' ); // close div#left-column
		
		$form .= Xml::openElement( 'div', array( 'id' => 'right-column', 'class' => 'payflow-cc-form-section' ));
		$form .= $this->generatePaymentContainer();
		
		return $form;
	}

	public function generateFormSubmit() {
		// submit button
		$form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit'));
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' )); 	
		//$form .= Xml::submitButton( wfMsg( 'payflowpro_gateway-submit-button' ));
		$form .= Xml::element( 'input', array( 'class' => 'input-button button-navyblue', 'value' => wfMsg( 'payflowpro_gateway-submit-button'), 'onclick' => 'submit_form( this )', 'type' => 'submit'));
		$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
		$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			wfMsg( 'payflowpro_gateway-donate-click' ); 
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
		global $wgPayflowGatewayHeader, $wgOut;
		// intro text
		if ( $wgPayflowGatewayHeader ) {
			$header = str_replace( '@language', $this->form_data[ 'language' ], $wgPayflowGatewayHeader );
			$template = $wgOut->parse( $header );
			
			// make sure that we actually have a matching template to display so we don't display the 'redlink'
			if ( !preg_match( '/redlink\=1/', $template )) {
				$wgOut->addHtml( $template );
			}
		}	
	}

	protected function generatePersonalContainer() {
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));			;
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-cc-form-header-personal' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		
		$form .= $this->generatePersonalFields();
		
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generatePersonalFields() {
		// first name			
		$form = '<tr>';
		$form .= '<td>' . Xml::label( wfMsg( 'payflowpro_gateway-donor-fname' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['fname'] . '</span></td>';
		$form .= "</tr>";

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

	protected function generatePaymentContainer() {
		$form = '';
		// credit card info
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-payment-info' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-payment' ), wfMsg( 'payflowpro_gateway-cc-form-header-payment' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc' ) );
		
		$form .= $this->generatePaymentFields();
		
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-cc
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-payment-info

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
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['card_num'] . '</span>' . 
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['card'] . '</span></td>';
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