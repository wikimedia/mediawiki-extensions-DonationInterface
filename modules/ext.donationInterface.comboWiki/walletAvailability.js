/* global google */
/**
 * Whether the donor's browser can pay with a wallet (Apple Pay, Google Pay).
 *
 * PaymentMethodForm asks before showing a wallet anywhere, so a donor never sees
 * a wallet option they can't use. The wallet components ask again when they mount
 * and show the donor an error if the answer changed (e.g. the SDK was blocked after
 * the first check).
 */
const { loadScript, getAdyenCheckout, getAdyenApplePayConfiguration } = require( './utils.js' );

// Kept for the page's lifetime so the client and readiness check are shared by
// PaymentMethodForm and GooglePayComponent, and survive the component being reopened
let googlePaymentsClient = null;
let googlePayReadyPromise = null;

// Kept for the page's lifetime so PaymentMethodForm asks Adyen only once
let adyenApplePayReadyPromise = null;

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

/**
 * Asks Adyen's ApplePay component rather than ApplePaySession directly: it loads Apple's
 * SDK itself (so ApplePaySession exists outside Safari) and needs the page on https.
 *
 * @param {Object} adyenConfiguration From Special:Donate, without Vue proxies
 * @param {Object} donation
 * @return {Promise<boolean>} Whether Adyen offers Apple Pay and the donor's browser can use it
 */
function isAdyenApplePayAvailable( adyenConfiguration, donation ) {
	// Only set when the selected gateway is Adyen
	if ( !adyenConfiguration ) {
		return Promise.resolve( false );
	}
	// Adyen didn't offer Apple Pay for this country, currency and amount
	const offered = ( ( adyenConfiguration.paymentMethodsResponse || {} ).paymentMethods || [] )
		.some( ( paymentMethod ) => paymentMethod.type === 'applepay' );
	if ( !offered ) {
		return Promise.resolve( false );
	}
	if ( !adyenApplePayReadyPromise ) {
		adyenApplePayReadyPromise = getAdyenCheckout( adyenConfiguration, donation.country )
			// Never mounted: AdyenApplePayComponent builds its own with the callbacks
			.then( ( checkout ) => new window.AdyenWeb.ApplePay(
				checkout,
				getAdyenApplePayConfiguration( adyenConfiguration, donation )
			).isAvailable() )
			.then( () => true )
			.catch( ( e ) => {
				// isAvailable() rejects rather than resolving false, e.g. no Apple Pay
				// on this device, or Adyen's or Apple's SDK blocked by a content blocker.
				// Not kept, so a later check can try again.
				mw.log.error( 'combowiki adyen applepay unavailable', e );
				adyenApplePayReadyPromise = null;
				return false;
			} );
	}
	return adyenApplePayReadyPromise;
}

module.exports = {
	getGooglePaymentsClient,
	isApplePayAvailable,
	isGooglePayAvailable,
	isAdyenApplePayAvailable
};
