/* global AdyenCheckout */
( function ( $, mw ) {
	/**
	 * Set up Adyen Checkout
	 *
	 * @param config
	 * @returns AdyenCheckout
	 */
	function getCheckout( config ) {
		config.onSubmit = onSubmit;
		config.onAdditionalDetails = onAdditionalDetails;
		config.onError = onError;
		config.showPayButton = false;
		return new AdyenCheckout( config );
	}

	/**
	 *
	 * TODO: should we do this mapping server-side
	 * using SmashPig's ReferenceData?
	 * @param adyenBrandCode
	 * @returns string
	 */
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

	// drop in the components container ready
	// to be bound to.
	$( '.submethods' ).before(
		'<div id="component-container" />'
	);

	// Load Adyen card component
	var config, checkout, adyen;
	config = mw.config.get( 'adyenConfiguration' );
	checkout = getCheckout( config );
	adyen = checkout.create( 'card' ).mount( '#component-container' );

	$( '#paymentSubmit' ).show();
	$( '#paymentSubmitBtn' ).on( 'click', function ( evt ) {
		mw.donationInterface.validation.validate();
		adyen.submit( evt );
	} );
} )( jQuery, mediaWiki );
