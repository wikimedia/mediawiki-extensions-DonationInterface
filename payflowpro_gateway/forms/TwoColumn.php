<?php

class PayflowProGateway_Form_TwoColumn extends PayflowProGateway_Form {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		
		parent::__construct( $form_data, $form_errors );
		
		// we only want to load this JS if the form is being rendered
		$wgOut->addHeadItem( 'validatescript', '<script type="text/javascript" src="' . 
				     $wgScriptPath . 
 				     '/extensions/DonationInterface/payflowpro_gateway/validate_input.js?283"></script>' );
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
		$form .= <<<EOT
<script type="text/javascript">
var fname = document.getElementById('fname');
var lname = document.getElementById('lname');
var amountOther = document.getElementById('amountOther');
if (fname.value == '') {
	fname.style.color = '#999999';
	fname.value = 'First';
}
if (lname.value == '') {
	lname.style.color = '#999999';
	lname.value = 'Last';
}
if (amountOther.value == '') {
	amountOther.style.color = '#999999';
	amountOther.value = 'Other';
}
</script>
EOT;
		$form .= Xml::closeElement( 'div' ); // close div#mw-creditcard-form
		//$form .= $this->generateDonationFooter();
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
		global $wgRequest;
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));			;
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-cc-form-header-personal' ));
		$sourceId = $wgRequest->getText( 'utm_source_id', 13 );
		$form .= Xml::Tags( 'p', array( 'id' => 'payflowpro_gateway-cc_otherways' ), wfMsg( 'payflowpro_gateway-paypal', $sourceId ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		
		$form .= $this->generatePersonalFields();
		
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generatePersonalFields() {
		global $wgScriptPath, $wgPayflowGatewayTest;
		$scriptPath = "$wgScriptPath/extensions/DonationInterface/payflowpro_gateway";
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';
		$form = '';
		
		// name	
		$form .= $this->getNameField();
		
		// email
		$form .= $this->getEmailField();
		
		//comment message
		$form .= '<tr>';
		$form .= '<td colspan="2">';
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ));
		$form .= '</td>';
		$form .= '</tr>';
		
		//comment
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg('payflowpro_gateway-comment'), 'comment' ) . '</td>';
		$form .= '<td>' . Xml::input( 'comment', '30', $this->form_data[ 'comment' ], array( 'type' => 'text', 'maxlength' => '200', 'class' => 'fullwidth' )) . '</td>';
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
		$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" )) . '</td>';
		$form .= '</tr>';
		
		// card number
		$form .= $this->getCardnumberField();
		
		// cvv
		$form .= $this->getCvvField();
		
		// expiry
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) . '</td>';
		$form .= '<td>' . $this->generateExpiryMonthDropdown() . $this->generateExpiryYearDropdown() . '</td>';
		$form .= '</tr>';
		
		// street
		$form .= $this->getStreetField();

		// city
		$form .= $this->getCityField();

		// state
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-state' ), 'state' ) . '</td>';
		$form .= '<td>' . $this->generateStateDropdown() . ' ' . wfMsg( 'payflowpro_gateway-state-in-us' ) . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['state'] . '</span></td>';
		$form .= '</tr>';
			
		// zip
		$form .= $this->getZipField();
		
		// country
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-country' ), 'country' ) . '</td>';
		$form .= '<td>' . $this->generateCountryDropdown() . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['country'] . '</span></td>';
	    $form .= '</tr>';

		return $form;
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
		if ( $this->form_data['amount'] != 250 && $this->form_data['amount'] != 100 && $this->form_data['amount'] != 75 && $this->form_data['amount'] != 35 && $this->form_data['amountOther'] > 0 ) {
			$otherChecked = true;
			$amount = $this->form_data['amountOther'];
		}
		$form = '<tr>';
		$form .= '<td colspan="2"><span class="creditcard-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label(wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount') . '</td>'; 
		$form .= '<td>' . Xml::radio( 'amount', 250, $this->form_data['amount'] == 250 ) . '250 ' . 
			Xml::radio( 'amount', 100, $this->form_data['amount'] == 100 ) . '100 ' .
			Xml::radio( 'amount', 75,  $this->form_data['amount'] == 75 ) . '75 ' .
			Xml::radio( 'amount', 35, $this->form_data['amount'] == 35 ) . '35 ' .
			'</td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label"></td>';
		$form .= '<td>' . Xml::radio( 'amount', $amount, $otherChecked, array( 'id' => 'otherRadio' ) ) . Xml::input( 'amountOther', '7', $this->form_data['amountOther'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "Other" )', 'onblur' => 'document.getElementById("otherRadio").value = this.value;if (this.value > 0) document.getElementById("otherRadio").checked=true;', 'maxlength' => '10', 'id' => 'amountOther' ) ) . 
			' ' . $this->generateCurrencyDropdown() . '</td>';
		$form .= '</tr>';
		return $form;
	}
	
	protected function getCardnumberField() {
		global $wgPayflowGatewayTest;
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		
		$form = '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['card_num'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['card'] . '</span></td>';
		$form .= '</tr>';
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
		$form .= '<td>' . Xml::input( 'cvv', '5', $cvv, array( 'type' => 'text', 'maxlength' => '10', 'id' => 'cvv', 'autocomplete' => 'off') ) .
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
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "First" )', 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			Xml::input( 'lname', '30', $this->form_data['lname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "Last" )', 'maxlength' => '15', 'id' => 'lname' ) ) . '</td>';
		$form .= "</tr>";
		return $form;
	}
}