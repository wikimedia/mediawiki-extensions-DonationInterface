( function ( $ ) {
	$( function () {
		var donatedAmount = +$( '#amount' ).val(),
			donatedAmountFormatted = donatedAmount.toLocaleString(
			$( '#language' ).val() + '-' + $( '#country' ).val(),
			{
				currency: $( '#currency' ).val(),
				style: 'currency'
			}
		);

		$( '.mc-convert-donated-amount' ).text( donatedAmountFormatted );
	} );
} )( jQuery );
