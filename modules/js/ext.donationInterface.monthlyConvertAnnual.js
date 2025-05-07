( function ( $, mw ) {
	$( () => {
		let mc = mw.monthlyConvert, originalGetSendData = mc.getSendData, frequencyUnit = 'month',
			originalAmount = Number( $( '#amount' ).val() ), originalAmountFormatted,
			$otherAmountMonthlyInput = $( '#mc-other-amount-input' ), $otherAmountAnnualInput = $( '#mc-other-amount-input-annual' ),
			$otherMonthlySubmit = $( '.mc-donate-monthly-button' ), $otherAnnualSubmit = $( '.mc-donate-annual-button' ),
			$smallAmountMessage = $( '#mc-error-smallamount' );

		mc.getSendData = function ( amount ) {
			const data = originalGetSendData( amount );
			data.frequency_unit = frequencyUnit;
			return data;
		};

		originalAmountFormatted = mc.formatAmount(
			originalAmount,
			$( '#currency' ).val(),
			$( '#language' ).val() + '-' + $( '#country' ).val()
		);

		$( '.mc-convert-ask-annual' ).text( originalAmountFormatted );
		$( '#mc-yes-button-monthly' ).on( 'click keypress', ( e ) => {
			if ( e.which === 13 || e.type === 'click' ) {
				frequencyUnit = 'month';
				mc.postMonthlyConvertDonate( mc.presetAmount );
			}
		} );
		$( '#mc-yes-button-annual' ).on( 'click keypress', ( e ) => {
			if ( e.which === 13 || e.type === 'click' ) {
				frequencyUnit = 'year';
				mc.postMonthlyConvertDonate( originalAmount );
			}
		} );
		$otherAmountMonthlyInput.on( 'keyup', () => {
			const otherAmount = Number( $otherAmountMonthlyInput.val() );
			if ( otherAmount > 0 ) {
				frequencyUnit = 'month';
				$otherAmountAnnualInput.val( '' ).removeClass( 'errorHighlight' );
				$otherMonthlySubmit.show();
				$otherAnnualSubmit.hide();
				$smallAmountMessage.hide();
			}
		} );
		$otherAmountAnnualInput.on( 'keyup', () => {
			const otherAmount = Number( $otherAmountAnnualInput.val() );
			if ( otherAmount > 0 ) {
				frequencyUnit = 'year';
				$otherAmountMonthlyInput.val( '' ).removeClass( 'errorHighlight' );
				$otherAnnualSubmit.show();
				$otherMonthlySubmit.hide();
				$smallAmountMessage.hide();
			}
		} );
		$otherAnnualSubmit.on( 'click keypress', ( e ) => {
			if ( e.which === 13 || e.type === 'click' ) {
				const otherAmount = Number( $otherAmountAnnualInput.val() );

				if ( otherAmount < mc.minLocal ) {
					$otherAmountAnnualInput.addClass( 'errorHighlight' );
					$smallAmountMessage.show();
				} else {
					$smallAmountMessage.hide();
					$otherAmountAnnualInput.removeClass( 'errorHighlight' );
					mc.postMonthlyConvertDonate( otherAmount );
				}
			}
		} );
	} );
} )( jQuery, mediaWiki );
