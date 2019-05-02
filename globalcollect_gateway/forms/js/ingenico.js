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
				src: result.iframe,
				width: 318,
				height: 316,
				frameborder: 0,
				name: 'ingenico-iFrame',
				id: 'ingenico-iFrame'
			} );
		$( '#payment-form' ).append( $form );
	}

	function handleResult( result ) {
		if ( di.forms.isIframe() && !result.redirect ) {
			showIframe( result );
		} else {
			location.replace( result.redirect );
		}
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( handleResult );
	};

} )( jQuery, mediaWiki );
