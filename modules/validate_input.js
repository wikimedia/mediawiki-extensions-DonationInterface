window.addEvent = function(obj, evType, fn) {
	if (obj.addEventListener){
		obj.addEventListener(evType, fn, false);
		return true;
	} else if (obj.attachEvent){
		var r = obj.attachEvent("on"+evType, fn);
		return r;
	} else {
		return false;
	}
};

window.getIfSessionSet = function() {
	sajax_do_call( 'efPayflowGatewayCheckSession', [], checkSession );
};

window.clearField = function( field, defaultValue ) {
	if (field.value == defaultValue) {
		field.value = '';
		field.style.color = 'black';
	}
};
window.clearField2 = function( field, defaultValue ) {
	if (field.value != defaultValue) {
		field.value = '';
		field.style.color = 'black';
	}
};

window.switchToPayPal = function() {
	document.getElementById('payflow-table-cc').style.display = 'none';
	document.getElementById('payflowpro_gateway-form-submit').style.display = 'none';
	document.getElementById('payflowpro_gateway-form-submit-paypal').style.display = 'block';
};
window.switchToCreditCard = function() {
	document.getElementById('payflow-table-cc').style.display = 'table';
	document.getElementById('payflowpro_gateway-form-submit').style.display = 'block';
	document.getElementById('payflowpro_gateway-form-submit-paypal').style.display = 'none';
};

/*
 * Validates the personal information fields
 *
 * @input form The form containing the inputs to be checked
 *
 * @return boolean true if no errors, false otherwise (also uses an alert() to notify the user)
 */
window.validate_personal = function( form ){

    // TODO: this form should only report a single error for the email address?

	var output = '';
	var currField = '';
	var i = 0;
	var fields = ['fname','lname','street','city','zip', 'emailAdd'],
		numFields = fields.length;
	for( i = 0; i < numFields; i++ ) {
		// See if the field is empty or equal to the placeholder
		if( document.getElementById( fields[i] ).value == '' || document.getElementById( fields[i] ).value == mw.msg( 'donate_interface-donor-'+fields[i] ) ) {
			currField = mw.msg( 'donate_interface-error-msg-' + fields[i] );
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + currField + '.\r\n';
		}
	}
	
	var stateField = document.getElementById( 'state' );
	if ( stateField && stateField.type == 'select-one' ) { // state is a dropdown select
		var selectedState = stateField.options[stateField.selectedIndex].value;
		if ( selectedState == 'YY' || selectedState == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-state-province' ) + '.\r\n';
		}
	}

	var countryField = document.getElementById( 'country' );
	if ( countryField && countryField.type == 'select-one' ) { // country is a dropdown select
		if ( countryField.options[countryField.selectedIndex].value == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	} else { // country is a hidden or text input
		if ( countryField.value == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	}

	// validate email address
	var apos = form.emailAdd.value.indexOf("@");
	var dotpos = form.emailAdd.value.lastIndexOf(".");

	if( apos < 1 || dotpos-apos < 2 ) {
		output += mw.msg( 'donate_interface-error-msg-email' ) + '.\r\n';
	}
	
	// Make sure cookies are enabled
	document.cookie = 'wmf_test=1;';
	if ( document.cookie.indexOf( 'wmf_test=1' ) != -1 ) {
		document.cookie = 'wmf_test=; expires=Thu, 01-Jan-70 00:00:01 GMT;'; // unset the cookie
	} else {
		output += mw.msg( 'donate_interface-error-msg-cookies' ); // display error
	}

	if( output ) {
		alert( output );
		return false;
	}

	return true;
};

window.validate_form = function( form ) {
	if( form == null ){
		form = document.payment
	}

	var output = '';
	var currField = '';
	var i = 0;
	var fields = ['fname','lname','street','city','zip', 'emailAdd', 'card_num','cvv'],
		numFields = fields.length;
	for( i = 0; i < numFields; i++ ) {
		// See if the field is empty or equal to the placeholder
		if( document.getElementById( fields[i] ).value == '' || document.getElementById( fields[i] ).value == mw.msg( 'donate_interface-donor-'+fields[i] ) ) {
			currField = mw.msg( 'donate_interface-error-msg-' + fields[i] );
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + currField + '.\r\n';
		}
	}
	
	var stateField = document.getElementById( 'state' );
	console.debug(stateField);
	console.debug(stateField.type);
	if ( stateField && stateField.type == 'select-one' ) { // state is a dropdown select
		var selectedState = stateField.options[stateField.selectedIndex].value;
		console.debug(selectedState);
		if ( selectedState == 'YY' || selectedState == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-state-province' ) + '.\r\n';
		}
	}
	
	var countryField = document.getElementById( 'country' );
	if ( countryField && countryField.type == 'select-one' ) { // country is a dropdown select
		if ( countryField.options[countryField.selectedIndex].value == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	} else { // country is a hidden or text input
		if ( countryField.value == '' ) {
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	}

	// validate email address
	var apos = form.emailAdd.value.indexOf("@");
	var dotpos = form.emailAdd.value.lastIndexOf(".");

	if( apos < 1 || dotpos-apos < 2 ) {
		output += mw.msg( 'donate_interface-error-msg-email' ) + '.\r\n';
	}
	
	// Make sure cookies are enabled
	document.cookie = 'wmf_test=1;';
	if ( document.cookie.indexOf( 'wmf_test=1' ) != -1 ) {
		document.cookie = 'wmf_test=; expires=Thu, 01-Jan-70 00:00:01 GMT;'; // unset the cookie
	} else {
		output += mw.msg( 'donate_interface-error-msg-cookies' ); // display error
	}

	if( output ) {
		alert( output );
		return false;
	}

	return true;
};

window.submit_form = function( ccform ) {
	if ( validate_form( ccform )) {
		// weird hack!!!!!! for some reason doing just ccform.submit() throws an error....
		$j(ccform).submit();
	}
	return true;
};

window.disableStates = function( form ) {

		if ( document.payment.country.value != 'US' ) {
			document.payment.state.value = 'XX';
		} else {
			document.payment.state.value = 'YY';
		}

		return true;
};

window.showCards = function() {
	if ( document.getElementById('four_cards') && document.getElementById('two_cards') ) {
		var index = document.getElementById('input_currency_code').selectedIndex;
		if ( document.getElementById('input_currency_code').options[index].value == 'USD' ) {
			document.getElementById('four_cards').style.display = 'table-row';
			document.getElementById('two_cards').style.display = 'none';
		} else {
			document.getElementById('four_cards').style.display = 'none';
			document.getElementById('two_cards').style.display = 'table-row';	
		}
	}
};

window.cvv = '';

window.PopupCVV = function() {
	cvv = window.open("", 'cvvhelp','scrollbars=yes,resizable=yes,width=600,height=400,left=200,top=100');
	cvv.document.write( payflowproGatewayCVVExplain );
	cvv.focus();
};

window.CloseCVV = function() {
	if (cvv) {
		if (!cvv.closed) cvv.close();
		cvv = null;
	}
};

window.onfocus = CloseCVV;
