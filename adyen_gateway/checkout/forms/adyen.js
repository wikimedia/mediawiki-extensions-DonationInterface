/* global AdyenCheckout */
( function ( $, mw ) {
	$( '.submethods' ).before(
		'<div id="dropin-container" />'
	);

	// TODO should we do this mapping server-side
	// using SmashPig's ReferenceData?
	function mapAdyenSubmethod( adyenBrandCode ) {
		switch ( adyenBrandCode ) {
			case 'diners':
				return 'dc';
			case 'bijcard':
				return 'bij';
			case 'cartebancaire':
				return 'cb';
			case 'mc-debit':
				return 'mc';
			case 'visadankort':
				return 'visa';
			case 'visadebit':
			case 'vpay':
				return 'visa-debit';
			case 'visabeneficial':
				return 'visa-beneficial';
			case 'visaelectron':
				return 'visa-electron';
			default:
				return adyenBrandCode;
		}
	}

	function onSubmit( state, dropin ) {
		// Submit to our server
		if ( mw.donationInterface.validation.validate() && state.isValid ) {
			mw.donationInterface.forms.callDonateApi( handleApiResult, {
				// FIXME, add new API params for encrypted data instead of reusing old ones
				card_num: state.data.paymentMethod.encryptedCardNumber,
				expiration: state.data.paymentMethod.encryptedExpiryMonth,
				// HACK HACK HACK, using a totally dumb field here to not touch API yet
				processor_form: state.data.paymentMethod.encryptedExpiryYear,
				cvv: state.data.paymentMethod.encryptedSecurityCode,
				payment_submethod: mapAdyenSubmethod( state.data.paymentMethod.brand )
			} );
		}
	}

	function handleApiResult( data ) {
		// TODO: handle anything except success
		if ( data.redirect ) {
			document.location.replace( data.redirect );
		}
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
