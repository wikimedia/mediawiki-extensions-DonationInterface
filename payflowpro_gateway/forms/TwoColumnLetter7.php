<?php

class PayflowProGateway_Form_TwoColumnLetter7 extends PayflowProGateway_Form_OneStepTwoColumn {
	
	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter7.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}
	
	public function loadPlaceholders() {
		global $wgOut;
		// form placeholder values
		$other = wfMsg( 'payflowpro_gateway-other' );
		$first = wfMsg( 'payflowpro_gateway-first' );
		$last = wfMsg( 'payflowpro_gateway-last' );
		$street = wfMsg( 'payflowpro_gateway-donor-street' );
		$city = wfMsg( 'payflowpro_gateway-donor-city' );
		$zip = wfMsg( 'payflowpro_gateway-zip-code' );
		$email = wfMsg( 'payflowpro_gateway-donor-email' );
		$js = <<<EOT
<script type="text/javascript">
function loadPlaceholders() {
	var otherRadio = document.getElementById('otherRadio');
	var amountOther = document.getElementById('amountOther');
	var fname = document.getElementById('fname');
	var lname = document.getElementById('lname');
	var street = document.getElementById('street');
	var city = document.getElementById('city');
	var zip = document.getElementById('zip');
	var email = document.getElementById('emailAdd');
	if (typeof otherRadio != "undefined" && amountOther.value == '') {
		amountOther.style.color = '#999999';
		amountOther.value = '$other';
	}
	if (fname.value == '') {
		fname.style.color = '#999999';
		fname.value = '$first';
	}
	if (lname.value == '') {
		lname.style.color = '#999999';
		lname.value = '$last';
	}
	if (street.value == '') {
		street.style.color = '#999999';
		street.value = '$street';
	}
	if (city.value == '') {
		city.style.color = '#999999';
		city.value = '$city';
	}
	if (zip.value == '') {
		zip.style.color = '#999999';
		zip.value = '$zip';
	}
	if (email.value == '') {
		email.style.color = '#999999';
		email.value = '$email';
	}
}
addEvent( window, 'load', loadPlaceholders );

function formCheck( ccform ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'State', 'Zip', 'CardNum', 'Cvv' ];

	var fields = ["emailAdd","fname","lname","street","city","state","zip","card_num","cvv" ],
		numFields = fields.length,
		i,
		output = '',
		currField = '';
		
	var doCheck = true;
	if( typeof( document.payment.PaypalRedirect.value ) !== 'undefined' ) {
		if( document.payment.PaypalRedirect.value == 1 ) {
			doCheck = false;
		}
	}

	if( doCheck ) {
		for( i = 0; i < numFields; i++ ) {
			if( document.getElementById( fields[i] ).value == '' ) {
				currField = window['payflowproGatewayErrorMsg'+ msg[i]];
				output += payflowproGatewayErrorMsgJs + ' ' + currField + '.\\r\\n';
			}
		}
		
		if (document.getElementById('fname').value == '$first') {
			output += payflowproGatewayErrorMsgJs + ' first name.\\r\\n';
		}
		if (document.getElementById('lname').value == '$last') {
			output += payflowproGatewayErrorMsgJs + ' last name.\\r\\n';
		}
		if (document.getElementById('street').value == '$street') {
			output += payflowproGatewayErrorMsgJs + ' street address.\\r\\n';
		}
		if (document.getElementById('city').value == '$city') {
			output += payflowproGatewayErrorMsgJs + ' city.\\r\\n';
		}
		if (document.getElementById('zip').value == '$zip') {
			output += payflowproGatewayErrorMsgJs + ' zip code.\\r\\n';
		}
	
		// validate email address
		var apos = document.payment.emailAdd.value.indexOf("@");
		var dotpos = document.payment.emailAdd.value.lastIndexOf(".");
	
		if( apos < 1 || dotpos-apos < 2 ) {
			output += payflowproGatewayErrorMsgEmail;
		}
	}
	
	if( output ) {
		alert( output );
		return false;
	} else {
		return true;
	}
}
</script>
EOT;
		$wgOut->addHeadItem( 'placeholders', $js );
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

		$form .= Xml::tags( 'h2', array( 'id' => 'donate-head' ), wfMsg( 'payflowpro_gateway-make-your-donation' ));

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
		global $wgScriptPath, $wgPayflowGatewayPaypalURL, $wgRequest;
		$scriptPath = "$wgScriptPath/extensions/DonationInterface/payflowpro_gateway/includes";

		$form = '';

		/*
		$form .= '<tr>';
		$form .= '<td style="text-align:center;" colspan="2"><big><b>' . wfMsg( 'payflowpro_gateway-paypal-button' ) . '</b></big><br/><a href="#" onclick="document.payment.PaypalRedirect.value=1;document.payment.submit();"><img src="' . $scriptPath . '/paypal.png"/></a></td>';
		$form .= '</tr>';
		*/
		
		// amount
		$otherChecked = false;
		$amount = -1;
		if ( $this->form_data['amount'] != 250 && $this->form_data['amount'] != 150 && $this->form_data['amount'] != 100 && $this->form_data['amount'] != 75 && $this->form_data['amount'] != 50 && $this->form_data['amount'] != 35 && $this->form_data['amount'] != 20 && $this->form_data['amountOther'] > 0 ) {
			$otherChecked = true;
			$amount = $this->form_data['amountOther'];
		}
		$form .= '<tr>';
		$form .= '<td colspan="2"><span class="creditcard-error-msg">' . $this->form_errors['invalidamount'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label"><div style="padding-top:4px;">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-amount' ), 'amount' ) . '</div></td>';
		$form .= '<td>' . 
			'<table cellspacing="3" cellpadding="0" border="0" style="margin-bottom:0.2em;"><tr>' .
			'<td>'.Xml::radio( 'amount', 20, $this->form_data['amount'] == 20, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$20 '.'</td>'.
			'<td>'.Xml::radio( 'amount', 35,  $this->form_data['amount'] == 35, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$35 '.'</td>'.
			'<td>'.Xml::radio( 'amount', 50, $this->form_data['amount'] == 50, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$50 '.'</td>'.
			'<td>'.Xml::radio( 'amount', 75, $this->form_data['amount'] == 75, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$75 '.'</td>'.
			'</tr><tr>' .
			'<td>'.Xml::radio( 'amount', 100, $this->form_data['amount'] == 100, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$100 '.'</td>'.
			'<td>'.Xml::radio( 'amount', 150, $this->form_data['amount'] == 150, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$150 '.'</td>'.
			'<td>'.Xml::radio( 'amount', 250, $this->form_data['amount'] == 250, array( 'onfocus' => 'clearField2( document.getElementById(\'amountOther\'), "Other" )' ) ) . '$250 '.'</td>'.
			'<td>'.Xml::radio( 'amount', $amount, $otherChecked, array( 'id' => 'otherRadio' ) ) . Xml::input( 'amountOther', '7', $this->form_data['amountOther'], array( 'type' => 'text', 'onfocus' => 'clearField(this, "Other");document.getElementById("otherRadio").checked=true;', 'maxlength' => '10', 'onblur' => 'document.getElementById("otherRadio").value = this.value;', 'id' => 'amountOther' ) ).Html::hidden( 'currency_code', 'USD' ).'</td>'.
			'</tr></table>' .
			'</td>';
		$form .= '</tr>';
		
		// email opt-in
		/*
		$email_opt_value = ( $wgRequest->wasPosted() ) ? $this->form_data[ 'email-opt' ] : true;
		$form .= '<tr>';
		$form .= '<td class="label"> </td>';
		$form .= '<td class="check-option">' . Xml::check( 'email-opt', $email_opt_value );
		$form .= ' ';
		$form .= Xml::openElement( 'label', array( 'for' => 'email-opt' ) );
		$form .= wfMsg( 'donate_interface-email-agreement' );
		$form .= Xml::closeElement( 'label' );
		$form .= '</td>';
		$form .= '</tr>';
		*/

		// Payment type
		$form .= '<tr>';
		$form .= '<td class="label""><div style="padding-top:9px;">' . wfMsg( 'payflowpro_gateway-payment-type' ) . '</div></td>';
		$form .= '<td>' . 
			'<p style="border: 1px solid rgb(187, 187, 187); float: left; -moz-border-radius: 5px 5px 5px 5px; margin: 0 8px 0 0; padding: 5px 5px 5px 3px;">'.
			Xml::radio( 'card', 'cc1', $this->form_data['card'] == 'cc1', array( 'id' => 'cc1radio', 'onclick' => 'switchToCreditCard()' ) ) . '<label for="cc1radio">' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/card-visa.png" ) ). '</label>' .
			'&nbsp;<label for="cc1radio">' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/card-mastercard.png" ) ). '</label>' .
			'&nbsp;<label for="cc1radio">' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/card-amex.png" ) ). '</label>' .
			'&nbsp;<label for="cc1radio">' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/card-discover.png" ) ). '</label>' . 
			'</p>'.
			'<p style="border: 1px solid transparent; float: left; -moz-border-radius: 5px 5px 5px 5px; margin: 0; padding: 5px 5px 5px 3px;">'.
			Xml::radio( 'card', 'pp', $this->form_data['card'] == 'pp', array( 'id' => 'ppradio', 'onclick' => 'switchToPayPal()' ) ) . '<label for="ppradio">' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/card-paypal.png" ) ) . '</label>' .
			'</p>'.
			'</td>';
		$form .= '</tr>';
		
		$form .= '</table>';
		
		if ( $this->form_data['card'] == 'cc1' || $this->form_data['card'] == 'cc2' || $this->form_data['card'] == 'cc3' || $this->form_data['card'] == 'cc4' ) {
			$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc' ) );
		} else {
			$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-cc', 'style' => 'display: none;' ) );
		}
		
		$form .= '<tr>';
		$form .= '<td colspan="2"><h3 class="cc_header">' . wfMsg( 'payflowpro_gateway-cc-form-header-personal' ) . 
			Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/padlock.gif", 'style' => 'vertical-align:baseline;margin-left:8px;' ) ) . '</h3></td>';
		$form .= '</tr>';
		
		// card number
		$form .= $this->getCardNumberField();

		// expiry
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-expiration' ), 'expiration' ) . '</td>';
		$form .= '<td>' . $this->generateExpiryMonthDropdown() . ' / ' . $this->generateExpiryYearDropdown() . '</td>';
		$form .= '</tr>';
		
		// cvv
		$form .= $this->getCvvField();

		// name
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['fname'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['lname'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-name-on-card' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-first' ).'\' )', 'maxlength' => '25', 'class' => 'required', 'id' => 'fname' ) ) .
			Xml::input( 'lname', '30', $this->form_data['lname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-last' ).'\' )', 'maxlength' => '25', 'id' => 'lname' ) ) . '</td>';
		$form .= "</tr>";

		// street
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['street'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-billing-address' ), 'street' ) . '</td>';
		$form .= '<td>' . Xml::input( 'street', '30', $this->form_data['street'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-donor-street' ).'\' )', 'maxlength' => '100', 'id' => 'street', 'class' => 'fullwidth' ) ) .
			'</td>';
		$form .= '</tr>';

		// city
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['city'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label"> </td>';
		$form .= '<td>' . Xml::input( 'city', '18', $this->form_data['city'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-donor-city' ).'\' )', 'maxlength' => '40', 'id' => 'city' ) ) . ' ' .
			$this->generateStateDropdown() . ' ' .
			Xml::input( 'zip', '5', $this->form_data['zip'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-zip-code' ).'\' )', 'maxlength' => '10', 'id' => 'zip' ) ) .
			Html::hidden( 'country', 840 ) .
			'</td>';
		$form .= '</tr>';

		// country
		/*
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['country'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label"> </td>';
		$form .= '<td>' . $this->generateCountryDropdown() . '</td>';
	    $form .= '</tr>';
	    */
		
		// email
		$form .= '<tr>';
		$form .= '<td colspan=2><span class="creditcard-error-msg">' . $this->form_errors['emailAdd'] . '</span></td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-email-receipt' ), 'emailAdd' ) . '</td>';
		$form .= '<td>' . Xml::input( 'emailAdd', '30', $this->form_data['email'], array( 'type' => 'text', 'onfocus' => 'clearField( this, \''.wfMsg( 'payflowpro_gateway-donor-email' ).'\' )', 'maxlength' => '64', 'id' => 'emailAdd', 'class' => 'fullwidth' ) ) .
			Html::hidden( 'email-opt', 1 ) .
			'</td>';
		$form .= '</tr>';

		/*
		$comment_opt_value = ( $wgRequest->wasPosted() ) ? $this->form_data[ 'comment-option' ] : true;
		$form .= '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'comment-option', $comment_opt_value );
		$form .= ' ' . Xml::label( wfMsg( 'payflowpro_gateway-anon-message' ), 'comment-option' ) . '</td>';
		$form .= '</tr>';

		$form .= $this->getEmailOptField();
		*/

		return $form;
	}

	public function generateFormSubmit() {
		global $wgScriptPath;
	
		// cc submit button
		if ( $this->form_data['card'] == 'cc1' || $this->form_data['card'] == 'cc2' || $this->form_data['card'] == 'cc3' || $this->form_data['card'] == 'cc4' ) {
			$form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit' ) );
		} else {
			$form = Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit', 'style' => 'display: none;' ) );
		}
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ) );
		$form .= Xml::element( 'input', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/submit-donation-button.png", 'alt' => 'Submit donation', 'onclick' => 'document.payment.PaypalRedirect.value=0;return true;', 'type' => 'image' ) );
		$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
		$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/padlock.gif", 'style' => 'vertical-align:baseline;margin-right:4px;' ) ) . 'Your donation will be securely processed.';
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-submessage
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-form-submit
		
		// paypal submit button
		if ( $this->form_data['card'] == 'cc1' || $this->form_data['card'] == 'cc2' || $this->form_data['card'] == 'cc3' || $this->form_data['card'] == 'cc4' ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit-paypal', 'style' => 'display: none;' ) );
		} else {
			$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-form-submit-paypal' ) );
		}
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ) );
		$form .= Html::hidden( 'PaypalRedirect', 0 );
		$form .= Xml::element( 'input', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/continue-button.png", 'alt' => 'Submit donation', 'onclick' => 'document.payment.PaypalRedirect.value=1;return true;', 'type' => 'image' ) );
		$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
		$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/padlock.gif", 'style' => 'vertical-align:baseline;margin-right:4px;' ) ) . 'Your donation will be securely processed.';
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-submessage
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-submit-paypal
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

	public function generateDonationFooter() {
		global $wgScriptPath;
		$form = '';
		$form .= Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-donate-addl-info' ) );
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-donate-addl-info-text' ) );
		$form .= Xml::tags( 'div', array( 'style' => 'text-align:center;' ), '* * *' );
		$form .= Xml::tags( 'div', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-credit-storage-processing' ) );
		$form .= Xml::tags( 'div', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-otherways-alt' ) );
		$form .= Xml::tags( 'div', array( 'class' => '' ), wfMsg( 'payflowpro_gateway-question-comment' ) );
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-addl-info-text
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-addl-info
		return $form;
	}
	
	public function generateStateDropdown() {
		require_once( dirname( __FILE__ ) . '/../includes/stateAbbreviations.inc' );

		$states = statesMenuXML();

		$state_opts = Xml::option( '', '' );

		// generate dropdown of state opts
		foreach ( $states as $value => $state_name ) {
			if ( $value !== 'YY' && $value !== 'XX' ) {
				$selected = ( $this->form_data[ 'state' ] == $value ) ? true : false;
				$state_opts .= Xml::option( $value, $value, $selected );
			}
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
}
