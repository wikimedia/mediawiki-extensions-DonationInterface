/**
 * Validate the donation amount to make sure it is formatted correctly and at least a minimum amount.
 * TODO: also validate ceiling
 */
window.validateAmount = function () {
	var error = true,
		amount = $( 'input[name="amount"]' ).val(), // get the amount
		currency = '',
		rates = mw.config.get( 'wgDonationInterfaceCurrencyRates' ),
		amountRules = mw.config.get( 'wgDonationInterfaceAmountRules' ),
		minimumInDonationCurrency,
		minDisplay,
		message = mediaWiki.msg( 'donate_interface-smallamount-error' ),
		$amountMsg = $( '#amountMsg' ),
		threeDecimalCurrencies = [ 'BHD', 'CLF', 'IQD', 'KWD', 'LYD',
			'MGA', 'MRO', 'OMR', 'TND' ];

	// Check amount is at least the minimum
	if ( $( 'input[name="currency"]' ).length ) {
		currency = $( 'input[name="currency"]' ).val();
	}
	if ( $( 'select[name="currency"]' ).length ) {
		currency = $( 'select[name="currency"]' ).val();
	}

	// Normalize weird amount formats.
	// Don't mess with these unless you know what you're doing.
	/*jshint ignore:start*/
	amount = amount.replace( /[,.](\d)$/, '\:$10' );
	amount = amount.replace( /[,.](\d)(\d)$/, '\:$1$2' );
	if ( threeDecimalCurrencies.indexOf( currency ) > -1 ) {
		amount = amount.replace( /[,.](\d)(\d)(\d)$/, '\:$1$2$3' );
	}
	amount = amount.replace( /[,.]/g, '' );
	amount = amount.replace( /:/, '.' );
	$( 'input[name="amount"]' ).val( amount ); // set the new amount back into the form
	/*jshint ignore:end*/

	// Check amount is a real number, sets error as true (good) if no issues
	error = ( amount === null || isNaN( amount ) || amount.value <= 0 );

	if ( currency === amountRules.currency || ( typeof rates[ currency ] ) === 'undefined' ) {
		minimumInDonationCurrency = amountRules.min;
	} else {
		// Rates are all relative to USD, so we divide the configured minimum by its corresponding
		// rate to get the minimum in USD, then multiply by the rate of the donation currency to get
		// the minimum in the donation currency.
		minimumInDonationCurrency = amountRules.min / rates[ amountRules.currency ] * rates[ currency ];
	}
	// if we're on a new form, clear existing amount error
	$amountMsg.removeClass( 'errorMsg' ).addClass( 'errorMsgHide' ).text( '' );
	if ( ( amount < minimumInDonationCurrency ) || error ) {
		// Round to two decimal places (TODO: no decimals for some currencies)
		minDisplay = Math.round( minimumInDonationCurrency * 100 ) / 100;
		message = message.replace( '$1', minDisplay + ' ' + currency );
		$amountMsg.removeClass( 'errorMsgHide' ).addClass( 'errorMsg' ).text( message );

		error = true;
		// See if we're on a webitects accordion form
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
 * FIXME: Bad name, this validates more than just personal info.
 * Move the good parts to ext.donationInterface.validation.js
 *
 * @return {boolean} true if no errors, false otherwise (also uses in-page error messages to notify the user)
 */
window.validate_personal = function () {
	var value, countryField, emailAdd, invalid, apos, dotpos, domain,
		errorsPresent = false,
		$formField,
		i,
		invalids = [ '..', '/', '\\', ',', '<', '>' ],
		rules = mediaWiki.config.get( 'wgDonationInterfaceValidationRules' ) || [];

	function clearError( field ) {
		$( '#' + field ).removeClass( 'errorHighlight' );
		$( '#' + field + 'Msg' )
			.removeClass( 'errorMsg' )
			.addClass( 'errorMsgHide' );
	}

	function setError( field, message ) {
		errorsPresent = true;
		$( '#' + field ).addClass( 'errorHighlight' );
		$( '#' + field + 'Msg' )
			.removeClass( 'errorMsgHide' )
			.addClass( 'errorMsg' )
			.text( message );
	}

	function isEmpty( field, value ) {
		return !$.trim( value ) ||
			value === mediaWiki.msg( 'donate_interface-donor-' + field );
	}

	// Generically defined rules set by GatewayAdapter->getClientSideValidationRules
	$.each( rules, function ( fieldKey, ruleList ) {
		clearError( fieldKey );
		$.each( ruleList, function ( i, rule ) {
			var failed = false;
			$formField = $( '#' + fieldKey );
			if ( $formField.length === 0 ) {
				// Radio button special case. First see if the group exists ...
				$formField = $( 'input[name=' + fieldKey + ']' );
				if ( $formField.length > 0 ) {
					// ... then filter to just the selected button
					$formField = $formField.filter( ':checked' );
				} else {
					// Field doesn't exist by id or by name
					return;
				}
			}
			value = $formField.val();
			if ( rule.required ) {
				if ( isEmpty( fieldKey, value ) ) {
					failed = true;
				}
			}
			if ( rule.pattern && !isEmpty( fieldKey, value ) ) {
				if ( !value.match( new RegExp( rule.pattern ) ) ) {
					failed = true;
				}
			}
			if ( failed ) {
				setError( fieldKey, rule.message );
			}
		} );
	} );

	// FIXME: wouldn't $( '#country' ).val() work for both types?
	countryField = document.getElementById( 'country' );
	if ( countryField && countryField.type === 'select-one' ) { // country is a dropdown select
		if ( !$.trim( countryField.options[ countryField.selectedIndex ].value ) ) {
			setError(
				'country',
				mediaWiki.msg( 'donate_interface-error-msg-country' )
			);
		} else {
			clearError( 'country' );
		}
	} else { // country is a hidden or text input
		if ( !$.trim( countryField.value ) ) {
			setError(
				'country',
				mediaWiki.msg( 'donate_interface-error-msg-country' )
			);
		} else {
			clearError( 'country' );
		}
	}

	// validate email address
	// FIXME: replace with regex in wgDonationInterfaceValidationRules
	emailAdd = document.getElementById( 'email' );
	if (
		emailAdd &&
		$.trim( emailAdd.value ) &&
		emailAdd.value !== mediaWiki.msg( 'donate_interface-donor-email' )
	) {
		invalid = false;

        var specialCharacterRegex = [ '(^[\\-])|',
        '([`!@#$%^&*()_+\\-=\\[\\]{};\':"\\\\|,.<>\\/?~]+$)' ];

        if ( new RegExp( specialCharacterRegex.join( '' ) ).test( emailAdd.value ) ) {
            setError(
                'email',
                mediaWiki.msg( 'donate_interface-error-msg-invalid-email' )
            );
            invalid = true;
        }
		apos = emailAdd.value.indexOf( '@' );
		dotpos = emailAdd.value.lastIndexOf( '.' );

		if ( apos < 1 || dotpos - apos < 2 ) {
			setError(
				'email',
				mediaWiki.msg( 'donate_interface-error-msg-invalid-email' )
			);
			invalid = true;
		}

		domain = emailAdd.value.slice( Math.max( 0, apos + 1 ) );

		for ( i = 0; i < invalids.length && !invalid; i++ ) {
			if ( domain.indexOf( invalids[ i ] ) !== -1 ) {
				setError(
					'email',
					mediaWiki.msg( 'donate_interface-error-msg-invalid-email' )
				);
				invalid = true;
				break;
			}
		}

		if ( /[0-9]$/.test( domain ) ) {
			setError(
				'email',
				mediaWiki.msg( 'donate_interface-error-msg-invalid-email' )
			);
		}
	}

	// Make sure cookies are enabled
	document.cookie = 'wmf_test=1;';
	if ( document.cookie.indexOf( 'wmf_test=1' ) !== -1 ) {
		document.cookie = 'wmf_test=; expires=Thu, 01-Jan-70 00:00:01 GMT;'; // unset the cookie
		clearError( 'cookie' );
	} else {
		errorsPresent = true; // display error
		setError( 'cookie', mediaWiki.msg( 'donate_interface-error-msg-cookies' ) );
	}

	return !errorsPresent;
};
