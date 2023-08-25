( function ( $ ) {
	$( function () {
		var mc = mw.monthlyConvert, originalAmount, presetAmount;
		originalAmount = parseFloat( $( '#amount' ).val() );
		presetAmount = mc.getConvertAsk( originalAmount );
		presetAmount = ( presetAmount * 1.1 ).toFixed( 2 ); // Rounds to 2 decimal places
		presetAmount = parseFloat( presetAmount ); // Converts back to a number
		mc.presetAmount = presetAmount;
	} );
} )( jQuery );
