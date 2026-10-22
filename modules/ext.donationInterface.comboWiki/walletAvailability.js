/* global google */
/**
 * Whether the donor's browser can pay with a wallet (Apple Pay, Google Pay).
 *
 * PaymentMethodForm asks before showing a wallet anywhere, so a donor never sees
 * a wallet option they can't use. The wallet components ask again when they mount
 * and show the donor an error if the answer changed (e.g. the SDK was blocked after
 * the first check).
 */
const { loadScript } = require( './utils.js' );

// Kept for the page's lifetime so the client and readiness check are shared by
// PaymentMethodForm and GooglePayComponent, and survive the component being reopened
let googlePaymentsClient = null;
let googlePayReadyPromise = null;

/**
 * @param {Object} gravyConfiguration From Special:Donate, without Vue proxies
 * @return {Object} The google.payments.api.PaymentsClient for the page
 */
function getGooglePaymentsClient( gravyConfiguration ) {
	if ( !googlePaymentsClient ) {
		googlePaymentsClient = new google.payments.api.PaymentsClient( {
			environment: gravyConfiguration.googleEnvironment
		} );
	}
	return googlePaymentsClient;
}

/**
 * @param {Object} gravyConfiguration From Special:Donate, without Vue proxies
 * @return {Promise<boolean>} Whether Apple's SDK loaded and the browser supports Apple Pay
 */
function isApplePayAvailable( gravyConfiguration ) {
	// Only set when the selected gateway is Gravy
	if ( !gravyConfiguration ) {
		return Promise.resolve( false );
	}
	// <apple-pay-button> and, outside Safari, window.ApplePaySession are only defined
	// once Apple's SDK has run. Donate.php only preloads it, which doesn't execute it.
	// https://developer.apple.com/documentation/applepayontheweb/applepaysession/canmakepayments
	return loadScript( gravyConfiguration.appleScript )
		.then( () => !!window.ApplePaySession && window.ApplePaySession.canMakePayments() )
		.catch( ( e ) => {
			// e.g. the SDK blocked by a content blocker
			mw.log.error( 'combowiki applepay load failed', e );
			return false;
		} );
}

/**
 * @param {Object} gravyConfiguration From Special:Donate, without Vue proxies
 * @return {Promise<boolean>} Whether pay.js loaded and Google says the donor can pay
 */
function isGooglePayAvailable( gravyConfiguration ) {
	// Only set when the selected gateway is Gravy
	if ( !gravyConfiguration ) {
		return Promise.resolve( false );
	}
	if ( !googlePayReadyPromise ) {
		googlePayReadyPromise = loadScript( gravyConfiguration.googleScript )
			.then( () => getGooglePaymentsClient( gravyConfiguration ).isReadyToPay( {
				apiVersion: 2,
				apiVersionMinor: 0,
				allowedPaymentMethods: [ {
					type: 'CARD',
					parameters: {
						allowedCardNetworks: gravyConfiguration.googleAllowedNetworks,
						allowedAuthMethods: [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ],
						billingAddressRequired: true,
						billingAddressParameters: {
							format: 'FULL'
						}
					}
				} ]
			} ) )
			.then( ( response ) => !!( response && response.result ) )
			.catch( ( e ) => {
				// e.g. pay.js blocked by a content blocker, or the readiness check rejected.
				// Not kept, so a later check can try again.
				mw.log.error( 'combowiki googlepay readiness check failed', e );
				googlePayReadyPromise = null;
				return false;
			} );
	}
	return googlePayReadyPromise;
}

module.exports = {
	getGooglePaymentsClient,
	isApplePayAvailable,
	isGooglePayAvailable
};
