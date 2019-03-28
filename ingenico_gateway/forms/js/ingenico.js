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
			if ( result.formaction.length < 100 ) {
				$.ajax( {
					url: mw.util.wikiScript( 'api' ),
					data: {
						action: 'logPaymentsFormError',
						message: 'FORMACTION suspiciously short! response was: ' +
						    JSON.stringify( result ),
						userAgent: navigator.userAgent
					},
					dataType: 'json',
					type: 'POST'
				} );
			}
			if ( isIframe && !result.gateway_params.redirect ) {
				showIframe( result );
			} else {
				redirect( result );
			}
		} );
	};
} )( jQuery, mediaWiki );
