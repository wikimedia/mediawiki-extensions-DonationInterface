( function ( $, mw ) {
	var di = mw.donationInterface;

	function showIframe( result ) {
		// Remove any preexisting iFrames
		$( '#ingenico-iFrame' ).remove();
		// Don't let people edit name, address, or email, since we won't
		// see any changes they make while the iframe is open.
		di.forms.disableInput();

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
