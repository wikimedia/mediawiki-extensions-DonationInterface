( function ( $, mw ) {
	var mc = {},
		currency,
		originalAmount,
		presetAmount,
		tyUrl = mw.config.get( 'wgDonationInterfaceThankYouUrl' );

	mc.setConvertAsk = function ( amount, locale, currency ) {
		var convertAmountFormatted;

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

		convertAmountFormatted = presetAmount.toLocaleString(
			locale,
			{
				currency: currency,
				style: 'currency'
			}
		);

		$( '.mc-convert-ask' ).text( convertAmountFormatted );

		return presetAmount;
	};

	mc.postUpdonate = function ( amount ) {
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
					alert( mw.msg( 'donate_interface-monthly-convert-error' ) );
					document.location.assign( tyUrl );
				}
			},
			error: function () {
				// FIXME too
				alert( mw.msg( 'donate_interface-monthly-convert-error' ) );
				document.location.assign( tyUrl );
			}
		} );
	};

	$( function () {
		originalAmount = +$( '#amount' ).val();
		currency = $( '#currency' ).val();
		mc.setConvertAsk(
			originalAmount,
			$( '#language' ).val() + '-' + $( '#country' ).val(),
			currency
		);
		$( '.mc-no-button, .mc-close' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				document.location.assign( tyUrl );
			}
		} );
		$( '.mc-yes-button' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				mc.postUpdonate( presetAmount );
			}
		} );
		$( '.mc-donate-monthly-button' ).on( 'click keypress' , function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				var otherAmountField = $( '#mc-other-amount-input' ),
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
					$( '#mc-error-smallamount' ).show();
				} else if ( otherAmount > originalAmount ) {
					otherAmountField.addClass( 'errorHighlight' );
					$( '#mc-error-bigamount' ).show();
				} else {
					$( '.mc-error' ).hide();
					otherAmountField.removeClass( 'errorHighlight' );
					mc.postUpdonate( otherAmount );
				}
			}
		} );
        /* eslint-disable no-jquery/no-fade */
		$( '.mc-diff-amount-link' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				$( '.mc-choice' ).fadeOut( function () {
					$( '.mc-edit-amount' ).fadeIn();
					$( '.mc-back' ).fadeIn();
					$( '.mc-other-amount-input' ).focus();
				} );
			}
		} );
		$( '.mc-back' ).on( 'click keypress', function ( e ) {
			if ( e.which === 13 || e.type === 'click' ) {
				$( '.mc-back' ).fadeOut();
				$( '.mc-edit-amount' ).fadeOut( function () {
					$( '.mc-choice' ).fadeIn();
				} );
			}
		} );
		/* eslint-enable no-jquery/no-fade */

	} );
} )( jQuery, mediaWiki );
