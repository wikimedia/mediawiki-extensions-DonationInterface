( function ( $, mw ) {
	var ru = {},
		currency,
		originalAmount,
		presetAmount,
		tyUrl = mw.config.get( 'wgDonationInterfaceThankYouUrl' );

	ru.setUpsellAsk = function ( amount, locale, currency ) {
		var upsellAmountFormatted;

		if ( amount < 9 ) {
			presetAmount = 1.75;
		} else if ( amount <= 12 ) {
			presetAmount = 2;
		} else if ( amount <= 15 ) {
			presetAmount = 2.5;
		} else if ( amount <= 18 ) {
			presetAmount = 3;
		} else if ( amount <= 21 ) {
			presetAmount = 3.5;
		} else if ( amount <= 24 ) {
			presetAmount = 4;
		} else if ( amount <= 27 ) {
			presetAmount = 4.5;
		} else if ( amount <= 30 ) {
			presetAmount = 5;
		} else if ( amount <= 33 ) {
			presetAmount = 5.5;
		} else if ( amount <= 36 ) {
			presetAmount = 6;
		} else if ( amount <= 39 ) {
			presetAmount = 6.5;
		} else if ( amount <= 42 ) {
			presetAmount = 7;
		} else if ( amount <= 45 ) {
			presetAmount = 7.5;
		} else if ( amount <= 48 ) {
			presetAmount = 8;
		} else if ( amount <= 51 ) {
			presetAmount = 8.5;
		} else if ( amount <= 54 ) {
			presetAmount = 9;
		} else if ( amount <= 57 ) {
			presetAmount = 9.5;
		} else if ( amount <= 60 ) {
			presetAmount = 10;
		} else if ( amount <= 63 ) {
			presetAmount = 10.5;
		} else if ( amount <= 66 ) {
			presetAmount = 11;
		} else if ( amount <= 69 ) {
			presetAmount = 11.5;
		} else if ( amount <= 72 ) {
			presetAmount = 12;
		} else if ( amount <= 75 ) {
			presetAmount = 12.5;
		} else if ( amount <= 102 ) {
			presetAmount = 17;
		} else if ( amount <= 250 ) {
			presetAmount = 25;
		} else if ( amount <= 500 ) {
			presetAmount = 50;
		} else {
			presetAmount = 100;
		}

		upsellAmountFormatted = presetAmount.toLocaleString(
			locale,
			{
				currency: currency,
				style: 'currency'
			}
		);

		$( '.ru-upsell-ask' ).text( upsellAmountFormatted );

		return presetAmount;
	};

	ru.postUpdonate = function ( amount ) {
		var sendData = {
			action: 'di_recurring_convert',
			format: 'json',
			gateway: $( '#gateway' ).val(),
			amount: amount
		};
		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: sendData,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				var url;
				if (
					typeof data.error === 'undefined' &&
					typeof data.result !== 'undefined' &&
					!data.result.errors
				) {
					url = new mw.Uri( tyUrl );
					document.location.assign(
						url.extend( { recurringConversion: 1 } ).toString()
					);
				} else {
					// FIXME - alert sux. Not much donor can do at this point.
					// We should let 'em know the recurring conversion failed
					// but the initial donation worked, then show them the thank
					// you page.
					alert( mw.msg( 'donate_interface-recurring-upsell-error' ) );
					document.location.assign( tyUrl );
				}
			},
			error: function () {
				// FIXME too
				alert( mw.msg( 'donate_interface-recurring-upsell-error' ) );
				document.location.assign( tyUrl );
			}
		} );
	};

	$( function () {
		originalAmount = +$( '#amount' ).val();
		currency = $( '#currency' ).val();
		ru.setUpsellAsk(
			originalAmount,
			$( '#language' ).val() + '-' + $( '#country' ).val(),
			currency
		);
		$( '.ru-no-button, .ru-close' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				document.location.assign( tyUrl );
			}
		} );
		$( '.ru-yes-button' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				ru.postUpdonate( presetAmount );
			}
		} );
		$( '.ru-donate-monthly-button' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				var otherAmountField = $( '#ru-other-amount-input' ),
					otherAmount = +otherAmountField.val(),
					rates = mw.config.get( 'wgDonationInterfaceCurrencyRates' ),
					rate,
					minUsd = mw.config.get( 'wgDonationInterfacePriceFloor' );

				if ( rates[ currency ] ) {
					rate = rates[ currency ];
				} else {
					rate = 1;
				}
				if ( otherAmount < minUsd * rate ) {
					otherAmountField.addClass( 'errorHighlight' );
					$( '#ru-error-smallamount' ).show();
				} else if ( otherAmount > originalAmount ) {
					otherAmountField.addClass( 'errorHighlight' );
					$( '#ru-error-bigamount' ).show();
				} else {
					$( '.ru-error' ).hide();
					otherAmountField.removeClass( 'errorHighlight' );
					ru.postUpdonate( otherAmount );
				}
			}
		} );
        /* eslint-disable no-jquery/no-fade */
		$( '.ru-diff-amount-link' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				$( '.ru-choice' ).fadeOut( function () {
					$( '.ru-edit-amount' ).fadeIn();
					$( '.ru-back' ).fadeIn();
					$( '.ru-other-amount-input' ).focus();
				} );
			}
		} );
		$( '.ru-back' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				$( '.ru-back' ).fadeOut();
				$( '.ru-edit-amount' ).fadeOut( function () {
					$( '.ru-choice' ).fadeIn();
				} );
			}
		} );
		/* eslint-enable no-jquery/no-fade */

	} );
} )( jQuery, mediaWiki );
