/* global describe it expect jest beforeEach */
const { when } = require( 'jest-when' );

const ADYEN_CONFIGURATION = {
	script: { src: 'https://example.org/adyen.js' },
	locale: 'en-US'
};

/**
 * Settles the script tag loadScript appended, as jsdom never fetches it
 *
 * @param {string} eventName 'onload' or 'onerror'
 */
function settleAdyenScript( eventName ) {
	document.body.querySelector( 'script[src="https://example.org/adyen.js"]' )[ eventName ]( new Event( 'x' ) );
}

describe( 'ComboWiki Adyen checkout', () => {
	let utils, AdyenCheckout;

	// The checkout is kept for the page, so each case loads a fresh copy of the module
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '';
		utils = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );
		AdyenCheckout = jest.fn( () => Promise.resolve( { checkout: true } ) );
		window.AdyenWeb = { AdyenCheckout };
	} );

	it( 'loads Adyen\'s SDK once and shares one checkout for the donation\'s country', async () => {
		const first = utils.getAdyenCheckout( ADYEN_CONFIGURATION, 'US' );
		const second = utils.getAdyenCheckout( ADYEN_CONFIGURATION, 'US' );
		settleAdyenScript( 'onload' );

		expect( await first ).toEqual( { checkout: true } );
		expect( await second ).toEqual( { checkout: true } );
		expect( AdyenCheckout ).toHaveBeenCalledTimes( 1 );
		expect( AdyenCheckout ).toHaveBeenCalledWith( Object.assign( {}, ADYEN_CONFIGURATION, { countryCode: 'US' } ) );
		// The server's configuration is left as it was
		expect( ADYEN_CONFIGURATION.countryCode ).toBeUndefined();
	} );

	it( 'tries again when Adyen\'s SDK fails to load', async () => {
		const failed = utils.getAdyenCheckout( ADYEN_CONFIGURATION, 'US' );
		settleAdyenScript( 'onerror' );
		await expect( failed ).rejects.toBeDefined();

		const retried = utils.getAdyenCheckout( ADYEN_CONFIGURATION, 'US' );
		settleAdyenScript( 'onload' );
		expect( await retried ).toEqual( { checkout: true } );
	} );
} );

describe( 'ComboWiki Adyen Apple Pay configuration', () => {
	it( 'charges the donation\'s amount in minor units, in its currency and country', () => {
		mw.config.get.mockImplementation( () => [ 'JPY' ] );
		when( mw.config.get ).calledWith( 'adyenConfiguration' ).mockReturnValue(
			Object.assign( {}, ADYEN_CONFIGURATION, {
				appleRequiredBillingContactFields: [ 'name', 'postalAddress' ],
				appleRequiredShippingContactFields: [ 'name', 'email' ]
			} )
		);
		const { getAdyenApplePayConfiguration } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );

		expect( getAdyenApplePayConfiguration(
			{ amount: 10.5, currency: 'USD', country: 'US' }
		) ).toMatchObject( {
			countryCode: 'US',
			buttonLocale: 'en-US',
			buttonType: 'donate',
			requiredBillingContactFields: [ 'name', 'postalAddress' ],
			requiredShippingContactFields: [ 'name', 'email' ]
		} );
	} );
} );
