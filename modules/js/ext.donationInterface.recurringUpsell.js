( function ( $ ) {
	var ru = {};

	ru.setUpsellAsk = function ( amount ) {
		var upsellAmount, upsellAmountFormatted;

		if ( amount <= 5 ) {
			upsellAmount = 2.75;
		} else if ( amount <= 10 ) {
			upsellAmount = 3;
		} else if ( amount <= 20 ) {
			upsellAmount = 5;
		} else if ( amount <= 30 ) {
			upsellAmount = 7;
		} else if ( amount <= 50 ) {
			upsellAmount = 9;
		} else if ( amount <= 100 ) {
			upsellAmount = 11;
		} else if ( amount <= 250 ) {
			upsellAmount = 25;
		} else if ( amount <= 500 ) {
			upsellAmount = 50;
		} else {
			upsellAmount = 100;
		}

		// Need currency formatter function like the below.  Hardcoded for now.
		//upsellAmountFormatted = frb.formatCurrency(currency, upsellAmount, language);
		upsellAmountFormatted = '$' + upsellAmount;
		$( '.ru-upsell-ask' ).text( upsellAmountFormatted );

		return upsellAmount;
	};

	$( function () {
		ru.setUpsellAsk( $( '#amount' ).val() );

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
} )( jQuery );
