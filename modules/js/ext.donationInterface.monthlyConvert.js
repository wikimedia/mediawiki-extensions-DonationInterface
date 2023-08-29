( function ( $, mw ) {
	var mc = {},
		currency,
		originalAmount,
		// These config vars are set in GatewayPage::setClientVariables
		tyUrl = mw.config.get( 'wgDonationInterfaceThankYouUrl' ),
		// This is set to the ask amounts for the selected currency, or null
		// if there are no amounts set for it.
		convertAmounts = mw.config.get( 'wgDonationInterfaceMonthlyConvertAmounts' );

	mc.getConvertAsk = function ( amount ) {
		var i,
			numAmounts;
		if ( !convertAmounts ) {
			return 0;
		}
		numAmounts = convertAmounts.length;
		for ( i = 0; i < numAmounts; i++ ) {
			if ( amount <= convertAmounts[ i ][ 0 ] ) {
				return convertAmounts[ i ][ 1 ];
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

	// TODO Unify logic for determining whether or not to show monthly convert. This
	// is just a sanity check to see if the required DOM elements are there.
	mc.canShowModal = function () {
		return $( '.mc-modal-screen' ).length > 0;
	};
	mc.init = function () {
		var presetAmount,
			locale = $( '#language' ).val() + '-' + $( '#country' ).val();
		originalAmount = +$( '#amount' ).val();
		currency = $( '#currency' ).val();
		presetAmount = mc.presetAmount || mc.getConvertAsk( originalAmount );
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
						$( '#mc-error-largeamount' ).hide();
						$smallAmountMessage.show();
					} else if ( otherAmount > originalAmount ) {
						$otherAmountField.addClass( 'errorHighlight' );
						$( '#mc-error-smallamount' ).hide();
						$( '#mc-error-largeamount' ).show();
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

	};
	$( function () {
		if ( mw.config.get( 'showMConStartup' ) ) {
			mc.init();
		}
	} );
	mw.monthlyConvert = mc;
} )( jQuery, mediaWiki );
