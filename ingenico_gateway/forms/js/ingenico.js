( function ( $, mw ) {
	var di = mw.donationInterface;

	function showIframe( result ) {
		// Remove any preexisting iFrames
		$( '#ingenico-div' ).remove();

		var $div = $( '<div id="ingenico-div">'),
			$form = $( '<iframe>' )
			.attr( {
				src: result.formaction,
				frameborder: 0,
				name: 'ingenico-iFrame',
				id: 'ingenico-iFrame'
			} );
		$div.append( $form );
		$( '#payment-form' ).append( $div );
	}

	if ( di.forms.isIframe() ) {
		di.forms.submit = function () {
			di.forms.callDonateApi( function ( result ) {
				showIframe( result );
			} );
		};
	}
} )( jQuery, mediaWiki );
