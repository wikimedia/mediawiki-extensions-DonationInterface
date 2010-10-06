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
		$form .= $this->generateCommentFields();
		$form .= $this->getCaptchaHTML();
		$form .= $this->generateFormSubmit();
		$form .= $this->generateFormEnd();
		return $form;
	}
	
	public function generateFormStart() {
		global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut;
		$form = $this->generateBannerHeader();
		
		$form .= Xml::Tags( 'p', array( 'id' => 'payflowpro_gateway-cc_otherways' ), wfMsg( 'payflowpro_gateway-paypal' ));
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
		//$form .= $this->generateDonationFooter();
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
		global $wgScriptPath, $wgPayflowGatewayTest;
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';
		$form = '';
		
		// name	
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-name' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "First" )', 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			Xml::input( 'lname', '30', $this->form_data['lname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "Last" )', 'maxlength' => '15', 'id' => 'lname' ) ) . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['fname'] . '</span></td>';
		$form .= "</tr>";
		
		// email
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-email' ), 'emailAdd' ) . '</td>';
		$form .= '<td>' . Xml::input( 'emailAdd', '30', $this->form_data['email'], array( 'type' => 'text', 'maxlength' => '64', 'id' => 'emailAdd', 'class' => 'fullwidth' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['emailAdd'] . '</span></td>';
		$form .= '</tr>';

		// street
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-street' ), 'street' ) . '</td>';
		$form .= '<td>' . Xml::input( 'street', '30', $this->form_data['street'], array( 'type' => 'text', 'maxlength' => '30', 'id' => 'street', 'class' => 'fullwidth' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['street'] . '</span></td>';
		$form .= '</tr>';

		// city
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-city' ), 'city' ) . '</td>';
		$form .= '<td>' . Xml::input( 'city', '30', $this->form_data['city'], array( 'type' => 'text', 'maxlength' => '20', 'id' => 'city', 'class' => 'fullwidth' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['city'] . '</span></td>';
		$form .= '</tr>';

		// state
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-state' ), 'state' ) . '</td>';
		$form .= '<td>' . $this->generateStateDropdown() . ' ' . wfMsg( 'payflowpro_gateway-state-in-us' ) . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['state'] . '</span></td>';
		$form .= '</tr>';
			
		// zip
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-postal' ), 'zip' ) . '</td>';
		$form .= '<td>' . Xml::input( 'zip', '30', $this->form_data['zip'], array( 'type' => 'text', 'maxlength' => '9', 'id' => 'zip', 'class' => 'fullwidth' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['zip'] . '</span></td>';
		$form .= '</tr>';
		
		// country
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) . '</td>';
		$form .= '<td>' . $this->generateCountryDropdown() . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['country'] . '</span></td>';
	    $form .= '</tr>';

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
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';

		$form = '';
		
		// amount
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label(wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount') . '</td>'; 
		$form .= '<td>' . Xml::radio( 'amount', 250 ) . '$250 ' . 
			Xml::radio( 'amount', 100 ) . '$100 ' .
			Xml::radio( 'amount', 75 ) . '$75 ' .
			Xml::radio( 'amount', 35 ) . '$35 ' .
			Xml::radio( 'amount', -1, null, array( 'id' => 'otherRadio' ) ) . Xml::input( 'amountOther', '7', $this->form_data['amount'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "0.00" )', 'onblur' => 'document.getElementById("otherRadio").value = this.value', 'maxlength' => '10', 'id' => 'amount' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		
		// currency
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label(wfMsg( 'payflowpro_gateway-donor-currency-label' ), 'currency' ) . '</td>'; 
		$form .= '<td>' . $this->generateCurrencyDropdown() . '</td>';
		$form .= '</tr>';
		
		// card logos
		$form .= '<tr>';
		$form .= '<td />';
		$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" )) . '</td>';
		$form .= '</tr>';
		
		// card number
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-card-num' ), 'card_num' ) . '</td>';
		$form .= '<td>' . Xml::input( 'card_num', '30', $card_num, array( 'type' => 'text', 'maxlength' => '100', 'id' => 'card_num', 'class' => 'fullwidth', 'autocomplete' => 'off' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['card_num'] . '</span>' . 
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['card'] . '</span></td>';
		$form .= '</tr>';
		
		// cvv
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-security' ), 'cvv' ) . '</td>';
		$form .= '<td>' . Xml::input( 'cvv', '5', $cvv, array( 'type' => 'text', 'maxlength' => '10', 'id' => 'cvv', 'autocomplete' => 'off') ) .
			' ' . '<a href="javascript:PopupCVV();">' . wfMsg( 'payflowpro_gateway-cvv-link' ) . '</a>' .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['cvv'] . '</span></td>';
		$form .= '</tr>';
		
		// expiry
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) . '</td>';
		$form .= '<td>' . $this->generateExpiryMonthDropdown() . $this->generateExpiryYearDropdown() . '</td>';
		$form .= '</tr>';

		return $form;
	}
	
	public function generateCommentFields() {
		global $wgRequest;
		
		$form = Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-comment_form' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-comments' ), wfMsg( 'donate_interface-comment-title' ));
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-comment' ) );

		//comment
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg('payflowpro_gateway-comment'), 'comment' ) . '</td>';
		$form .= '<td class="comment-field">' . Xml::input( 'comment', '30', $this->form_data[ 'comment' ], array( 'type' => 'text', 'maxlength' => '200', 'class' => 'fullwidth' )) . '</td>';
		$form .= '</tr>';
		
		// anonymous
		$comment_opt_value = ( $this->form_data[ 'numAttempt' ] ) ? $this->form_data[ 'comment-option' ] : true;
		$form .= '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'comment-option', $comment_opt_value );
		$form .= ' ' . Xml::label( wfMsg( 'donate_interface-anon-message' ), 'comment-option' ) . '</td>';
		$form .= '</tr>';

		// email agreement
		$email_opt_value = ( $this->form_data[ 'numAttempt' ]) ? $this->form_data[ 'email-opt' ] : true;
		$form .= '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'email-opt', $email_opt_value );
		$form .= ' ';
		// put the label inside Xml::openElement so any HTML in the msg might get rendered (right, Germany?)
		$form .= Xml::openElement( 'label', array( 'for' => 'email-opt' ));
		$form .= wfMsg( 'donate_interface-email-agreement' );
		$form .= Xml::closeElement( 'label' );
		$form .= '</td>';
		$form .= '</tr>';

		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'div' );	
		return $form;
	}
}