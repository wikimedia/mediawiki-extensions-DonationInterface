/**
 * Experiment to determine whether different placeholder text improves donation
 * rates in Japan. Once tests are concluded and optimal text is decided, please
 * make the optimal text standard and delete this and the extension.json module
 * configuration for ext.donationInterface.jaVariant01A and
 * ext.donationInterface.adyenCheckoutWithJaVariant01A.
 */
( function ( mw, $ ) {
	mw.donationInterface = mw.donationInterface || {};
	mw.donationInterface.extraTranslations = {
		'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' )
	};
	$( '#last_name' ).attr( 'placeholder', '鈴木' );
	$( '#first_name' ).attr( 'placeholder', '太郎' );
} )( mediaWiki, jQuery );
