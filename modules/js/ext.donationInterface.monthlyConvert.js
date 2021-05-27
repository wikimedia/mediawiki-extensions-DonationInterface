( function ( $, mw ) {
	var mc = {},
		currency,
		originalAmount,
		tyUrl = mw.config.get( 'wgDonationInterfaceThankYouUrl' ),
		// If one-time amount <= left amount, suggest right amount for monthly
		convertAmounts = {
			USD: [ // also CAD, AUD, NZD
				[ 2.74, 0 ],
				[ 9, 1.75 ],
				[ 12, 2 ],
				[ 15, 2.5 ],
				[ 18, 3 ],
				[ 21, 3.5 ],
				[ 24, 4 ],
				[ 27, 4.5 ],
				[ 30, 5 ],
				[ 33, 5.5 ],
				[ 36, 6 ],
				[ 39, 6.5 ],
				[ 42, 7 ],
				[ 45, 7.5 ],
				[ 48, 8 ],
				[ 51, 8.5 ],
				[ 54, 9 ],
				[ 57, 9.5 ],
				[ 60, 10 ],
				[ 63, 10.5 ],
				[ 66, 11 ],
				[ 69, 11.5 ],
				[ 72, 12 ],
				[ 75, 12.5 ],
				[ 102, 17 ],
				[ 250, 25 ],
				[ 499, 50 ],
				[ Infinity, 0 ]
			],
			GBP: [ // also EUR
				[ 1.99, 0 ],
				[ 9, 1.75 ],
				[ 12, 2 ],
				[ 15, 2.5 ],
				[ 18, 3 ],
				[ 21, 3.5 ],
				[ 24, 4 ],
				[ 27, 4.5 ],
				[ 30, 5 ],
				[ 33, 5.5 ],
				[ 36, 6 ],
				[ 39, 6.5 ],
				[ 42, 7 ],
				[ 45, 7.5 ],
				[ 48, 8 ],
				[ 51, 8.5 ],
				[ 54, 9 ],
				[ 57, 9.5 ],
				[ 60, 10 ],
				[ 63, 10.5 ],
				[ 66, 11 ],
				[ 69, 11.5 ],
				[ 72, 12 ],
				[ 75, 12.5 ],
				[ 102, 17 ],
				[ 250, 25 ],
				[ 499, 50 ],
				[ Infinity, 0 ]
			],
			JPY: [
				[ 299, 0 ],
				[ 900, 150 ],
				[ 1200, 200 ],
				[ 1500, 250 ],
				[ 1800, 300 ],
				[ 2100, 350 ],
				[ 2400, 400 ],
				[ 2700, 450 ],
				[ 3000, 500 ],
				[ 3300, 550 ],
				[ 3600, 600 ],
				[ 3900, 650 ],
				[ 4200, 700 ],
				[ 4500, 750 ],
				[ 4800, 800 ],
				[ 5100, 850 ],
				[ 5400, 900 ],
				[ 5700, 950 ],
				[ 6000, 1000 ],
				[ 6300, 1050 ],
				[ 6600, 1100 ],
				[ 6900, 1150 ],
				[ 7200, 1200 ],
				[ 7500, 1250 ],
				[ 10800, 1800 ],
				[ 18000, 3000 ],
				[ 50000, 6000 ],
				[ Infinity, 0 ]
			]
		};
	convertAmounts.EUR = convertAmounts.GBP;
	convertAmounts.CAD = convertAmounts.USD;
	convertAmounts.AUD = convertAmounts.USD;
	convertAmounts.NZD = convertAmounts.USD;

	mc.getConvertAsk = function ( amount, currency ) {
		var i,
			amountsForCurrency = convertAmounts[ currency ],
			numAmounts;
		if ( !amountsForCurrency ) {
			return 0;
		}
		numAmounts = amountsForCurrency.length;
		for ( i = 0; i < numAmounts; i++ ) {
			if ( amount <= amountsForCurrency[ i ][ 0 ] ) {
				return amountsForCurrency[ i ][ 1 ];
			}
		}
		return 0;
	};

	mc.setConvertAsk = function ( suggestedAmount, currency, locale ) {
		var convertAmountFormatted = mc.formatAmount(
			suggestedAmount, currency, locale
		);
		$( '.mc-convert-ask' ).text( convertAmountFormatted );
		$( '.mc-modal-screen' ).show();
	};

	mc.formatAmount = function ( amount, currency, locale ) {
		var formattedAmount;

		try {
			formattedAmount = amount.toLocaleString(
				locale,
				{
					currency: currency,
					style: 'currency'
				}
			);
		} catch ( e ) {
			// Assume a two decimal place currency for fallback
			formattedAmount = currency + ' ' + amount.toFixed( 2 );
		}
		return formattedAmount;
	};

	mc.postUpdonate = function ( amount ) {
		var sendData = {
			action: 'di_recurring_convert',
			format: 'json',
			gateway: $( '#gateway' ).val(),
			wmf_token: $( '#wmf_token' ).val(),
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
		var presetAmount,
			locale = $( '#language' ).val() + '-' + $( '#country' ).val();
		originalAmount = +$( '#amount' ).val();
		currency = $( '#currency' ).val();
		presetAmount = mc.getConvertAsk( originalAmount, currency );
		if ( presetAmount === 0 && tyUrl !== null ) {
			// They're donating in an unsupported currency, or are
			// outside of the range where it makes sense to ask for
			// a monthly donation. Just send them to the TY page.
			document.location.assign( tyUrl );
		} else {
			mc.setConvertAsk(
				presetAmount,
				currency,
				locale
			);
			$( '.mc-no-button, .mc-close' ).on( 'click keypress', function ( e ) {
				if ( e.which === 13 || e.type === 'click' ) {
					document.location.assign( tyUrl );
				}
			} );
			$( '.mc-yes-button' ).on( 'click keypress', function ( e ) {
				if ( e.which === 13 || e.type === 'click' ) {
					mc.postUpdonate( presetAmount );
				}
			} );
			$( '.mc-donate-monthly-button' ).on( 'click keypress', function ( e ) {
				if ( e.which === 13 || e.type === 'click' ) {
					var $otherAmountField = $( '#mc-other-amount-input' ),
						otherAmount = +$otherAmountField.val(),
						rates = mw.config.get( 'wgDonationInterfaceCurrencyRates' ),
						rate,
						minUsd = mw.config.get( 'wgDonationInterfacePriceFloor' ),
						minLocal,
						formattedMin,
						$smallAmountMessage;

					if ( rates[ currency ] ) {
						rate = rates[ currency ];
					} else {
						rate = 1;
					}
					minLocal = minUsd * rate;
					if ( otherAmount < minLocal ) {
						formattedMin = mc.formatAmount(
							minLocal, currency, locale
						);
						$otherAmountField.addClass( 'errorHighlight' );
						$smallAmountMessage = $( '#mc-error-smallamount' );
						$smallAmountMessage.text(
							$smallAmountMessage.text().replace( '$1', formattedMin )
						);
						$smallAmountMessage.show();
					} else if ( otherAmount > originalAmount ) {
						$otherAmountField.addClass( 'errorHighlight' );
						$( '#mc-error-bigamount' ).show();
					} else {
						$( '.mc-error' ).hide();
						$otherAmountField.removeClass( 'errorHighlight' );
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
		}

	} );
} )( jQuery, mediaWiki );
