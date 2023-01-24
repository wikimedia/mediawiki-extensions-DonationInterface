/**
 * Experiment to determine whether different placeholder text and extra name
 * fields improve donation rates in Japan. Once tests are concluded and optimal
 * text is decided, please make the optimal text standard and delete this and
 * the extension.json module configuration for ext.donationInterface.jaVariant02
 * and ext.donationInterface.adyenCheckoutWithJaVariant02.
 */
( function ( mw ) {
	mw.donationInterface = mw.donationInterface || {};
	mw.donationInterface.extraTranslations = {
		'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' )
	};
} )( mediaWiki );
