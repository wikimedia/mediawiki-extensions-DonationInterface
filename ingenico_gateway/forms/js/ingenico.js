( function ( $, mw ) {
	var di = mw.donationInterface,
		oldSubmit = di.forms.submit;

	function showIframe( result ) {
		// Remove any preexisting iFrames
		$( '#ingenico-div' ).remove();

		// Don't let people edit name, address, or email, since we won't
		// see any changes they make while the iframe is open.
		di.forms.disableInput();
		var $div = $( '<div id="ingenico-div">'),
			$form = $( '<iframe>' )
			.attr( {
				src: result.iframe,
				frameborder: 0,
				name: 'ingenico-iFrame',
				id: 'ingenico-iFrame'
			} );
		$div.append( $form );
		$( '#payment-form' ).append( $div );
	}

	function redirect( result ) {
		document.location.replace( result.redirect);
	}

	di.forms.submit = function () {
		var paymentMethod = $( '#payment_method' ).val(),
			isIframe = di.forms.isIframe();
		if ( !isIframe && paymentMethod !== 'cc' ) {
			return oldSubmit();
		}
		di.forms.callDonateApi( function ( result ) {
			var url = result.redirect || result.iframe;
			if ( url.length < 100 ) {
				$.ajax( {
					url: mw.util.wikiScript( 'api' ),
					data: {
						action: 'logPaymentsFormError',
						message: 'Redirect/iframe URL suspiciously short! response was: ' +
						    JSON.stringify( result ),
						userAgent: navigator.userAgent
					},
					dataType: 'json',
					type: 'POST'
				} );
			}
			if ( isIframe && !result.redirect ) {
				showIframe( result );
			} else {
				redirect( result );
			}
		} );
	};
} )( jQuery, mediaWiki );
