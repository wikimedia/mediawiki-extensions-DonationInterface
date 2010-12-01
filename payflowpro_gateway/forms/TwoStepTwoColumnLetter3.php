<?php

class PayflowProGateway_Form_TwoStepTwoColumnLetter3 extends PayflowProGateway_Form_TwoStepTwoColumn {
	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath() ) ) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoStepTwoColumnLetter3.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}
	
	public function loadPlaceholders() {
		global $wgOut;
		// form placeholder values
		$first = wfMsg( 'payflowpro_gateway-first' );
		$last = wfMsg( 'payflowpro_gateway-last' );
		$street = wfMsg( 'payflowpro_gateway-donor-street' );
		$city = wfMsg( 'payflowpro_gateway-donor-city' );
		$zip = wfMsg( 'payflowpro_gateway-zip-code' );
		$email = wfMsg( 'payflowpro_gateway-donor-email' );
		$js = <<<EOT
<script type="text/javascript">
function loadPlaceholders() {
	var fname = document.getElementById('fname');
	var lname = document.getElementById('lname');
	var street = document.getElementById('street');
	var city = document.getElementById('city');
	var zip = document.getElementById('zip');
	var email = document.getElementById('emailAdd');
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
	
	if( output ) {
		alert( output );
		return false;
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

		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ) );
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		$form .= $this->generateBillingFields();
		
		return $form;
	}
	
	public function generateFormSubmit() {
		global $wgScriptPath;
		
		$form = '<tr>';
		$form .= '<td class="label"> </td>';
		$form .= '<td>';
		
		// submit button
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-submit-button' ) );
		// $form .= Xml::submitButton( wfMsg( 'payflowpro_gateway-submit-button' ));
		$form .= '&nbsp;<br/>' . Xml::element( 'input', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/submit-donation-button.png", 'alt' => 'Submit donation', 'type' => 'image' ) );
		$form .= Xml::closeElement( 'div' ); // close div#mw-donate-submit-button
		$form .= Xml::openElement( 'div', array( 'class' => 'mw-donate-submessage', 'id' => 'payflowpro_gateway-donate-submessage' ) ) .
			Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/padlock.gif", 'style' => 'vertical-align:baseline;margin-right:4px;' ) ) . 'Your credit / debit card will be securely processed.';
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-donate-submessage
		
		$form .= '</td>';
		$form .= '</tr>';
		return $form;
	}

	public function generateFormEnd() {
		$form = '';
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info
		$form .= $this->generateFormClose();
		return $form;
	}

	protected function generateBillingFields() {
		global $wgScriptPath, $wgPayflowGatewayTest;

		$form = '';
		
		// amount
		$form .= '<tr>';
		$form .= '<td colspan="2">';
		$form .= '<table cellspacing="0" cellpadding="4" border="1" id="donation_amount">';
		$form .= '<tr>';
		$form .= '<td class="amount_header">'.wfMsg( 'payflowpro_gateway-description' ).'</td>';
		$form .= '<td class="amount_header" style="text-align:right;width:75px;">'.wfMsg( 'payflowpro_gateway-donor-amount' ).'</td>';
		$form .= '<td class="amount_header" style="text-align:right;width:75px;">'.wfMsg( 'payflowpro_gateway-donor-currency-label' ).'</td>';
		$form .= '</tr>';
		$form .= '<tr>';
		$form .= '<td class="amount_data">'.wfMsg( 'payflowpro_gateway-donation' ).'</td>';
		$form .= '<td class="amount_data" style="text-align:right;width:75px;">'.$this->form_data['amount'] . 
			Html::hidden( 'amount', $this->form_data['amount'] ) . 
			'</td>';
		$form .= '<td class="amount_data" style="text-align:right;width:75px;">'.$this->form_data[ 'currency' ] .
			Html::hidden( 'currency_code', $this->form_data['currency'] ) . 
			'</td>';
		$form .= '</tr>';
		$form .= '</table>';
		$form .= '</td>';
		$form .= '</tr>';
		
		$form .= '<tr>';
		$form .= '<td colspan="2"><h3 class="cc_header">' . wfMsg( 'payflowpro_gateway-cc-form-header-personal' ) . 
			Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/padlock.gif", 'style' => 'vertical-align:baseline;margin-left:8px;' ) ) . '</h3></td>';
		$form .= '</tr>';

		// card logos
		$form .= '<tr>';
		$form .= '<td />';
		if ( $this->form_data[ 'currency' ] == 'USD' ) {
			$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos.gif" ) ) . '</td>';
		} else {
			$form .= '<td>' . Xml::element( 'img', array( 'src' => $wgScriptPath . "/extensions/DonationInterface/payflowpro_gateway/includes/credit_card_logos3.gif" ) ) . '</td>';
		}
		$form .= '</tr>';

		// card number
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$form .= '';
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
			'</td>';
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
