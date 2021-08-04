( function ( $, mw ) {
	$( function () {
		var mc = mw.monthlyConvert, originalAmount, currency, formattedAsk, presetAmount,
			locale = $( '#language' ).val() + '-' + $( '#country' ).val();
		originalAmount = +$( '#amount' ).val();
		currency = $( '#currency' ).val();
		presetAmount = mc.getConvertAsk( originalAmount, currency );
		formattedAsk = mc.formatAmount(
			presetAmount, currency, locale
		);
		$( '.mc-no-button' ).text(
			mw.msg( 'donate_interface-monthly-convert-no-button-variant-011' )
		);
		$( '.mc-yes-button' ).text(
			mw.msg( 'donate_interface-monthly-convert-yes-button-variant-011' ).replace(
				'<span class=\"mc-convert-ask\"></span>', formattedAsk
			)
		);
	} );
} )( jQuery, mediaWiki );
