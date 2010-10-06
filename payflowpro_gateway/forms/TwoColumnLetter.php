<?php

class PayflowProGateway_Form_TwoColumnLetter extends PayflowProGateway_Form_TwoColumn {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		
		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath())) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter.css' );
		}
			
		parent::__construct( $form_data, $form_errors );
		
		// update the list of hidden fields we need to use in this form.
		$this->updateHiddenFields();
	}

	public function generateFormStart() {
		global $wgOut, $wgRequest;
		$form = parent::generateBannerHeader();
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_container'));
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_form', 'class' => 'payflowpro_gateway-cc_form_column'));
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
			$form .= Xml::closeElement( 'div' );  // close div#mw-payflow-general-error
		}

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );
		
		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		
		$form .= $this->generateBillingContainer();
		return $form;
	}
        
	public function generateFormEnd() {
		global $wgRequest, $wgOut;
		$form = '';
		
		$form .= $this->generateFormClose();

		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter_inside' ));
		
		$text_template = $wgRequest->getText( 'text_template' );
		// if the user has uselang set, honor that, otherwise default to the language set for the form defined by 'language' in the query string
		if ( $wgRequest->getText( 'language' )) $text_template .= '/' . $this->form_data[ 'language' ];
		
		$template = ( strlen( $text_template )) ? $wgOut->parse( '{{'.$text_template.'}}' ) : '';
		// if the template doesn't exist, prevent the display of the red link
		if ( preg_match( '/redlink\=1/', $template )) $template = NULL;
		$form .= $template;
		
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-cc_form_letter
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-cc_form_letter_inside
		return $form;
	}
	
	protected function generateBillingContainer() {
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));			;
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-cc-form-header-personal' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		
		$form .= $this->generateBillingFields();
		
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generateBillingFields() {
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
		
		// amount
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label(wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount') . '</td>'; 
		$form .= '<td>' . Xml::radio( 'amount', 250 ) . '250 ' . 
			Xml::radio( 'amount', 100 ) . '100 ' .
			Xml::radio( 'amount', 75 ) . '75 ' .
			Xml::radio( 'amount', 35 ) . '35 ' .
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
	
	/**
	 * Generate form closing elements
	 */
	public function generateFormClose() {
		$form = '';
		// add hidden fields			
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Xml::hidden( $field, $value );
		}
			
		$form .= Xml::closeElement( 'form' ); // close form 'payment'
		$form .= <<<EOT
<script type="text/javascript">
var fname = document.getElementById('fname');
var lname = document.getElementById('lname');
if (fname.value == '') {
	fname.style.color = '#999999';
	fname.value = 'First';
}
if (lname.value == '') {
	lname.style.color = '#999999';
	lname.value = 'Last';
}
</script>
EOT;
		//$form .= $this->generateDonationFooter();

		$form .= Xml::closeElement( 'div' ); //close div#mw-creditcard
		$form .= Xml::closeElement( 'div' ); //close div#payflowpro_gateway-cc_form_form
		$form .= Xml::closeElement( 'div' ); //close div#payflowpro_gateway-cc_form_container
		return $form;
	}
}
