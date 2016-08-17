( function ( $, mw ) {
	var di = mw.donationInterface;

	function showIframe( result ) {
		// Remove any preexisting iFrames
		$( '#ingenico-iFrame' ).remove();

		var $form = $( '<iframe>' )
			.attr( {
				src: result.formaction,
				width: 318,
				height: 314,
				frameborder: 0,
				name: 'ingenico-iFrame',
				id: 'ingenico-iFrame'
			} );
		$( '#payment-form' ).append( $form );
	}

	if ( di.forms.isIframe() ) {
		di.forms.submit = function () {
			di.forms.callDonateApi( function ( result ) {
				showIframe( result );
			} );
		};
	}
} )( jQuery, mediaWiki );
