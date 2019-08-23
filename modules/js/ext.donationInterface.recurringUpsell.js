( function ( $, mw ) {
	var ru = {},
		originalAmount,
		presetAmount,
		tyUrl = mw.config.get( 'wgDonationInterfaceThankYouUrl' );

	ru.setUpsellAsk = function ( amount, locale, currency ) {
		var upsellAmountFormatted;

		if ( amount < 3 ) {
			presetAmount = 1;
		} else if ( amount <= 5 ) {
			presetAmount = 2.75;
		} else if ( amount <= 10 ) {
			presetAmount = 3;
		} else if ( amount <= 20 ) {
			presetAmount = 5;
		} else if ( amount <= 30 ) {
			presetAmount = 7;
		} else if ( amount <= 50 ) {
			presetAmount = 9;
		} else if ( amount <= 100 ) {
			presetAmount = 11;
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
		originalAmount = $( '#amount' ).val();
		ru.setUpsellAsk(
			originalAmount,
			$( '#language' ).val() + '-' + $( '#country' ).val(),
			$( '#currency' ).val()
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
				var otherAmountField = $( '.ru-other-amount-input' ),
					otherAmount = otherAmountField.val(),
					rates = mw.config.get( 'wgDonationInterfaceCurrencyRates' ),
					rate,
					minUsd = mw.config.get( 'wgDonationInterfacePriceFloor' ),
					currency = $( 'input[name="currency"]' ).val();

				if ( rates[ currency ] ) {
					rate = rates[ currency ];
				} else {
					rate = 1;
				}
				if ( otherAmount < minUsd * rate ) {
					otherAmountField.addClass( 'errorHighlight' );
					$( '.ru-error-smallamount' ).show();
				} else if ( otherAmount > originalAmount ) {
					otherAmountField.addClass( 'errorHighlight' );
					$( '.ru-error-bigamount' ).show();
				} else {
					$( '.ru-error-bigamount' ).hide();
					$( '.ru-error-smallamount' ).hide();
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
