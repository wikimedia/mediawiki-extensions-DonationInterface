( function ( $, mw ) {
	var di = mw.donationInterface,
		oldSubmit = di.forms.submit;

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

	function redirect( result ) {
		document.location.replace( result.formaction );
	}

	di.forms.submit = function () {
		var paymentMethod = $( '#payment_method' ).val(),
			isIframe = di.forms.isIframe();
		if ( !isIframe && paymentMethod !== 'cc' ) {
			return oldSubmit();
		}
		di.forms.callDonateApi( function ( result ) {
			if ( isIframe ) {
				showIframe( result );
			} else {
				redirect( result );
			}
		} );
	};
} )( jQuery, mediaWiki );
