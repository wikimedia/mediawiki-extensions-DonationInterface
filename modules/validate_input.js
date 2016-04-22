/*global wgCurrencyMinimums:true, alert:true*/
window.addEvent = function ( obj, evType, fn ) {
	if ( obj.addEventListener ) {
		obj.addEventListener( evType, fn, false );
		return true;
	}

	if ( obj.attachEvent ) {
		return obj.attachEvent( 'on' + evType, fn );
	}

	return false;
};

window.clearField = function ( field, defaultValue ) {
	if ( field.value === defaultValue ) {
		field.value = '';
		field.style.color = 'black';
	}
};
window.clearField2 = function ( field, defaultValue ) {
	if ( field.value !== defaultValue ) {
		field.value = '';
		field.style.color = 'black';
	}
};

window.switchToPayPal = function () {
	document.getElementById( 'payment-table-cc' ).style.display = 'none';
	document.getElementById( 'payment_gateway-form-submit' ).style.display = 'none';
	document.getElementById( 'payment_gateway-form-submit-paypal' ).style.display = 'block';
};
window.switchToCreditCard = function () {
	document.getElementById( 'payment-table-cc' ).style.display = 'table';
	document.getElementById( 'payment_gateway-form-submit' ).style.display = 'block';
	document.getElementById( 'payment_gateway-form-submit-paypal' ).style.display = 'none';
};

/**
 * Validate the donation amount to make sure it is formatted correctly and at least a minimum amount.
 * TODO: also validate ceiling
 */
window.validateAmount = function () {
	var error = true,
		amount = $( 'input[name="amount"]' ).val(), // get the amount
		currency_code = '',
		rate,
		minUsd = mw.config.get( 'wgDonationInterfacePriceFloor' );

	// Normalize weird amount formats.
	// Don't mess with these unless you know what you're doing.
	/*jshint ignore:start*/
	amount = amount.replace( /[,.](\d)$/, '\:$10' );
	amount = amount.replace( /[,.](\d)(\d)$/, '\:$1$2' );
	amount = amount.replace( /[,.]/g, '' );
	amount = amount.replace( /:/, '.' );
	$( 'input[name="amount"]' ).val( amount ); // set the new amount back into the form
	/*jshint ignore:end*/

	// Check amount is a real number, sets error as true (good) if no issues
	error = ( amount === null || isNaN( amount ) || amount.value <= 0 );

	// Check amount is at least the minimum
	if ( $( 'input[name="currency_code"]' ).length ) {
		currency_code = $( 'input[name="currency_code"]' ).val();
	}
	if ( $( 'select[name="currency_code"]' ).length ) {
		currency_code = $( 'select[name="currency_code"]' ).val();
	}
	// FIXME: replace with mw.config.get( "wgDonationInterfaceCurrencyRates" )
	if ( ( typeof wgCurrencyMinimums[ currency_code ] ) === 'undefined' ) {
		rate = 1;
	} else {
		rate = wgCurrencyMinimums[ currency_code ];
	}
	if ( ( amount < minUsd * rate ) || error ) {
		alert( mediaWiki.msg( 'donate_interface-smallamount-error' ).replace( '$1', minUsd * rate + ' ' + currency_code ) );
		error = true;
		// See if we're on a webitects accordian form
		if ( $( '#step1wrapper' ).length ) {
			$( '#step1wrapper' ).slideDown();
			$( '#paymentContinue' ).show();
			// If we're on a GlobalCollect iframe form, slide up the 3rd step to force the user to
			// generate a new iframe after they change the form.
			if ( $( '#payment iframe' ).length ) {
				$( '#step3wrapper' ).slideUp();
			}
		}
		$( '#other-amount' ).val( '' );
		$( '#other-amount' ).focus();
	}
	return !error;
};

/**
 * Validates the personal information fields
 *
 * @return {boolean} true if no errors, false otherwise (also uses in-page error messages to notify the user)
 */
window.validate_personal = function () {
	var value, stateField, selectedState, countryField, $emailAdd, invalid, apos, dotpos, domain,
		errorsPresent = false,
		currField = '',
		i = 0,
		fields = [ 'fname', 'lname', 'street', 'city', 'zip', 'email' ],
		msgFields = [ 'fnameMsg', 'lnameMsg', 'streetMsg', 'cityMsg', 'zipMsg', 'emailMsg' ],
		numFields = fields.length,
		invalids = [ '..', '/', '\\', ',', '<', '>' ];

	for ( i = 0; i < numFields; i++ ) {
		if ( $( '#' + fields[ i ] ).length > 0 ) { // Make sure field exists
			$( '#' + msgFields[ i ] ).removeClass( 'errorMsg' );
			$( '#' + msgFields[ i ] ).addClass( 'errorMsgHide' );
			$( '#' + fields[ i ] ).removeClass( 'errorHighlight' );
			// See if the field is empty or equal to the placeholder
			value = document.getElementById( fields[ i ] ).value;
			if (
				!$( '#' + fields[ i ] ).hasClass( 'optional' ) &&
				(
					!$.trim( value ) ||
					value === mediaWiki.msg( 'donate_interface-donor-' + fields[ i ] )
				)
			) {
				currField = mediaWiki.msg( 'donate_interface-error-msg-' + fields[ i ] );
				errorsPresent = true;
				$( '#' + fields[ i ] ).addClass( 'errorHighlight' );
				$( '#' + msgFields[ i ] ).removeClass( 'errorMsgHide' );
				$( '#' + msgFields[ i ] ).addClass( 'errorMsg' );
				$( '#' + msgFields[ i ] ).text( mediaWiki.msg( 'donate_interface-error-msg' ).replace( '$1', currField ) );
			}
		}
	}

	stateField = document.getElementById( 'state' );
	if ( stateField && stateField.type === 'select-one' ) { // state is a dropdown select
		selectedState = stateField.options[ stateField.selectedIndex ].value;
		if ( selectedState === 'YY' || !$.trim( selectedState ) ) {
			errorsPresent = true;
			$( '#state' ).addClass( 'errorHighlight' );
			$( '#stateMsg' ).removeClass( 'errorMsgHide' );
			$( '#stateMsg' ).addClass( 'errorMsg' );
			$( '#stateMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg' ).replace( '$1', mediaWiki.msg( 'donate_interface-state-province' ) ) );
		} else {
			$( '#state' ).removeClass( 'errorHighlight' );
			$( '#stateMsg' ).removeClass( 'errorMsg' );
			$( '#stateMsg' ).addClass( 'errorMsgHide' );
		}
	}

	countryField = document.getElementById( 'country' );
	if ( countryField && countryField.type === 'select-one' ) { // country is a dropdown select
		if ( !$.trim( countryField.options[ countryField.selectedIndex ].value ) ) {
			errorsPresent = true;
			$( '#country' ).addClass( 'errorHighlight' );
			$( '#countryMsg' ).removeClass( 'errorMsgHide' );
			$( '#countryMsg' ).addClass( 'errorMsg' );
			$( '#countryMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg-country' ) );
		} else {
			$( '#country' ).removeClass( 'errorHighlight' );
			$( '#countryMsg' ).removeClass( 'errorMsg' );
			$( '#countryMsg' ).addClass( 'errorMsgHide' );
		}
	} else { // country is a hidden or text input
		if ( !$.trim( countryField.value ) ) {
			errorsPresent = true;
			$( '#country' ).addClass( 'errorHighlight' );
			$( '#countryMsg' ).removeClass( 'errorMsgHide' );
			$( '#countryMsg' ).addClass( 'errorMsg' );
			$( '#countryMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg-country' ) );
		} else {
			$( '#country' ).removeClass( 'errorHighlight' );
			$( '#countryMsg' ).removeClass( 'errorMsg' );
			$( '#countryMsg' ).addClass( 'errorMsgHide' );
		}
	}

	// validate email address
	$emailAdd = document.getElementById( 'email' );
	if (
		$.trim( $emailAdd.value ) &&
		$emailAdd.value !== mediaWiki.msg( 'donate_interface-donor-email' )
	) {
		invalid = false;

		apos = $emailAdd.value.indexOf( '@' );
		dotpos = $emailAdd.value.lastIndexOf( '.' );

		if ( apos < 1 || dotpos - apos < 2 ) {
			errorsPresent = true;
			$( '#email' ).addClass( 'errorHighlight' );
			$( '#emailMsg' ).removeClass( 'errorMsgHide' );
			$( '#emailMsg' ).addClass( 'errorMsg' );
			$( '#emailMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg-invalid-email' ) );
			invalid = true;
		}

		domain = $emailAdd.value.substring( apos + 1 );

		for ( i = 0; i < invalids.length && !invalid; i++ ) {
			if ( domain.indexOf( invalids[ i ] ) !== -1 ) {
				errorsPresent = true;
				$( '#email' ).addClass( 'errorHighlight' );
				$( '#emailMsg' ).removeClass( 'errorMsgHide' );
				$( '#emailMsg' ).addClass( 'errorMsg' );
				$( '#emailMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg-invalid-email' ) );
				invalid = true;
				break;
			}
		}
	}

	// Make sure cookies are enabled
	document.cookie = 'wmf_test=1;';
	if ( document.cookie.indexOf( 'wmf_test=1' ) !== -1 ) {
		document.cookie = 'wmf_test=; expires=Thu, 01-Jan-70 00:00:01 GMT;'; // unset the cookie
		$( '#cookieMsg' ).addClass( 'errorMsgHide' );
	} else {
		errorsPresent = true; // display error
		$( '#cookieMsg' ).addClass( 'errorMsg' );
		$( '#cookieMsg' ).text( mediaWiki.msg( 'donate_interface-error-msg-cookies' ) );
	}

	if ( errorsPresent ) {
		return false;
	}

	return true;
};

window.validate_form = function ( form ) {
	form = form || document.payment;

	var element, value, stateField, selectedState, countryField, $emailAdd, apos, dotpos,
		output = '',
		currField = '',
		i = 0,
		fields = [
			'fname', 'lname', 'street', 'city', 'zip', 'card_num', 'cvv',
			'fiscal_number', 'account_name', 'account_number', 'authorization_id',
			'bank_code', 'bank_check_digit', 'branch_code', 'email'
		],
		numFields = fields.length;

	for ( i = 0; i < numFields; i++ ) {
		element = document.getElementById( fields[ i ] );

		if ( element ) { // Make sure field exists
			value = element.value;
			// See if the field is empty or equal to the placeholder
			if (
				!$( '#' + fields[ i ] ).hasClass( 'optional' ) &&
				(
					!$.trim( value ) ||
					value === mediaWiki.msg( 'donate_interface-donor-' + fields[ i ] )
				)
			) {
				currField = mediaWiki.msg( 'donate_interface-error-msg-' + fields[ i ] );
				output += mediaWiki.msg( 'donate_interface-error-msg-js' ) + ' ' + currField + '.\r\n';
			}
		}
	}

	stateField = document.getElementById( 'state' );
	if ( stateField && stateField.type === 'select-one' ) { // state is a dropdown select
		selectedState = stateField.options[ stateField.selectedIndex ].value;
		if ( selectedState === 'YY' || !$.trim( selectedState ) ) {
			output += mediaWiki.msg( 'donate_interface-error-msg-js' ) + ' ' + mediaWiki.msg( 'donate_interface-state-province' ) + '.\r\n';
		}
	}

	countryField = document.getElementById( 'country' );
	if ( countryField && countryField.type === 'select-one' ) { // country is a dropdown select
		if ( !$.trim( countryField.options[ countryField.selectedIndex ].value ) ) {
			output += mediaWiki.msg( 'donate_interface-error-msg-js' ) + ' ' + mediaWiki.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	} else { // country is a hidden or text input
		if ( !$.trim( countryField.value ) ) {
			output += mediaWiki.msg( 'donate_interface-error-msg-js' ) + ' ' + mediaWiki.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
		}
	}

	// validate email address
	$emailAdd = document.getElementById( 'email' );
	if ( $.trim( $emailAdd.value ) && $emailAdd.value !== mediaWiki.msg( 'donate_interface-donor-email' ) ) {
		apos = $emailAdd.value.indexOf( '@' );
		dotpos = $emailAdd.value.lastIndexOf( '.' );

		if ( apos < 1 || dotpos - apos < 2 ) {
			output += mediaWiki.msg( 'donate_interface-error-msg-invalid-email' ) + '.\r\n';
		}
	}

	// Make sure cookies are enabled
	document.cookie = 'wmf_test=1;';
	if ( document.cookie.indexOf( 'wmf_test=1' ) !== -1 ) {
		document.cookie = 'wmf_test=; expires=Thu, 01-Jan-70 00:00:01 GMT;'; // unset the cookie
	} else {
		output += mediaWiki.msg( 'donate_interface-error-msg-cookies' ); // display error
	}

	if ( output ) {
		alert( output );
		return false;
	}

	return true;
};

window.displayErrors = function () {
	// check for RapidHtml errors and display, if any
	var temp, e, f, g,
		amountErrorString = '',
		billingErrorString = '',
		paymentErrorString = '';

	// generate formatted errors to display
	temp = [];
	for ( e in amountErrors ) {
		if ( amountErrors[ e ] !== '' ) {
			temp[ temp.length ] = amountErrors[ e ];
		}
	}
	amountErrorString = temp.join( '<br />' );

	temp = [];
	for ( f in billingErrors ) {
		if ( billingErrors[ f ] !== '' ) {
			temp[ temp.length ] = billingErrors[ f ];
		}
	}
	billingErrorString = temp.join( '<br />' );

	temp = [];
	for ( g in paymentErrors ) {
		if ( paymentErrors[ g ] !== '' ) {
			temp[ temp.length ] = paymentErrors[ g ];
		}
	}
	paymentErrorString = temp.join( '<br />' );

	// show the errors
	if ( amountErrorString !== '' ) {
		$( '#topError' ).html( amountErrorString );
	} else if ( billingErrorString !== '' ) {
		$( '#topError' ).html( billingErrorString );
	} else if ( paymentErrorString !== '' ) {
		$( '#topError' ).html( paymentErrorString );
	}
};

window.submit_form = function ( ccform ) {
	if ( window.validate_form( ccform ) ) {
		// weird hack!!!!!! for some reason doing just ccform.submit() throws an error....
		$( ccform ).submit();
	}
	return true;
};

window.disableStates = function ( form ) {
	if ( document.payment.country.value !== 'US' ) {
		document.payment.state.value = 'XX';
	} else {
		document.payment.state.value = 'YY';
	}
	return true;
};

window.showCards = function () {
	var index;

	if ( document.getElementById( 'four_cards' ) && document.getElementById( 'two_cards' ) ) {
		index = document.getElementById( 'input_currency_code' ).selectedIndex;
		if ( document.getElementById( 'input_currency_code' ).options[ index ].value === 'USD' ) {
			document.getElementById( 'four_cards' ).style.display = 'table-row';
			document.getElementById( 'two_cards' ).style.display = 'none';
		} else {
			document.getElementById( 'four_cards' ).style.display = 'none';
			document.getElementById( 'two_cards' ).style.display = 'table-row';
		}
	}
};

window.cvv = '';

window.PopupCVV = function () {
	window.cvv = window.open(
			'', 'cvvhelp', 'scrollbars=yes,resizable=yes,width=600,height=400,left=200,top=100'
		);
	window.cvv.document.write( mediaWiki.msg( 'donate_interface-cvv-explain' ) );
	window.cvv.focus();
};

window.CloseCVV = function () {
	if ( window.cvv ) {
		if ( !window.cvv.closed ) {
			window.cvv.close();
		}
		window.cvv = null;
	}
};

window.onfocus = window.CloseCVV;
