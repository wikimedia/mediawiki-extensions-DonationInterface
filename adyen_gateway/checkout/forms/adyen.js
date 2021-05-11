/* global AdyenCheckout */
( function ( $, mw ) {
	$( '.submethods' ).before(
		'<div id="dropin-container" />'
	);
	function onSubmit( state, dropin ) {
		// Submit to our server
		if ( mw.donationInterface.validation.validate() && state.isValid ) {
			mw.donationInterface.forms.callDonateApi( handleApiResult, {
				// FIXME, add new API params for encrypted data instead of reusing old ones
				card_num: state.data.paymentMethod.encryptedCardNumber,
				expiration: state.data.paymentMethod.encryptedExpiryMonth,
				// HACK HACK HACK, using a totally dumb field here to not touch API yet
				processor_form: state.data.paymentMethod.encryptedExpiryYear,
				cvv: state.data.paymentMethod.encryptedSecurityCode
			} );
		}
	}
	function handleApiResult( data ) {

	}
	function onAdditionalDetails( state, dropin ) {
		// Handle 3D secure
	}
	function onError( error ) {
		var fieldId = error.fieldType,
			isValid = error.error === '',
			$fieldDiv = $( 'span[data-cse="' + fieldId + '"]' )
				.closest( '.adyen-checkout__input-wrapper' );
		if ( isValid ) {
			$fieldDiv.removeClass( 'errorHighlight' );
		} else {
			$fieldDiv.addClass( 'errorHighlight' );
		}
	}
	$( function () {
		var checkout,
			config = mw.config.get( 'adyenConfiguration' ),
			dropin;
		config.onSubmit = onSubmit;
		config.onAdditionalDetails = onAdditionalDetails;
		config.onError = onError;
		checkout = new AdyenCheckout( config );
		dropin = checkout
			.create( 'dropin' , {
				openFirstPaymentMethod: true,
				showPayButton: false
			} )
			.mount( '#dropin-container' );
		$( '#paymentSubmit' ).show();
		$( '#paymentSubmitBtn' ).on( 'click', function ( evt ) {
			mw.donationInterface.validation.validate();
			dropin.submit( evt );
		} );
	} );
} )( jQuery, mediaWiki );
