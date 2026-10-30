/* global describe it expect jest beforeEach */
const { when } = require( 'jest-when' );

const ADYEN_CONFIGURATION = {
	script: { src: 'https://example.org/adyen.js' },
	clientKey: 'test_key',
	environment: 'test',
	paymentMethodsResponse: { paymentMethods: [] },
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
		when( mw.config.get ).calledWith( 'adyenConfiguration' ).mockReturnValue( ADYEN_CONFIGURATION );
	} );

	it( 'loads Adyen\'s SDK once and shares one checkout for the donation\'s country', async () => {
		const first = utils.getAdyenCheckout( 'US' );
		const second = utils.getAdyenCheckout( 'US' );
		settleAdyenScript( 'onload' );

		expect( await first ).toEqual( { checkout: true } );
		expect( await second ).toEqual( { checkout: true } );
		expect( AdyenCheckout ).toHaveBeenCalledTimes( 1 );
		expect( AdyenCheckout ).toHaveBeenCalledWith( {
			clientKey: 'test_key',
			environment: 'test',
			paymentMethodsResponse: { paymentMethods: [] },
			countryCode: 'US',
			locale: 'en-US',
			translations: { 'en-US': {} }
		} );
		// The server's configuration is left as it was
		expect( ADYEN_CONFIGURATION.countryCode ).toBeUndefined();
	} );

	it( 'translates the card fields itself for a language Adyen doesn\'t support', async () => {
		when( mw.config.get ).calledWith( 'adyenConfiguration' ).mockReturnValue(
			Object.assign( {}, ADYEN_CONFIGURATION, { locale: 'he-IL' } )
		);
		const checkout = utils.getAdyenCheckout( 'IL' );
		settleAdyenScript( 'onload' );
		await checkout;

		expect( AdyenCheckout.mock.calls[ 0 ][ 0 ].translations[ 'he-IL' ] ).toMatchObject( {
			'creditCard.cardNumber.label': 'donate_interface-credit-card-number'
		} );
	} );

	it( 'tries again when Adyen\'s SDK fails to load', async () => {
		const failed = utils.getAdyenCheckout( 'US' );
		settleAdyenScript( 'onerror' );
		await expect( failed ).rejects.toBeDefined();

		const retried = utils.getAdyenCheckout( 'US' );
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

describe( 'ComboWiki Adyen error handling', () => {
	const OTHER_WAYS_URL = 'https://example.org/ways-to-give';
	let handleAdyenError, emitError;

	beforeEach( () => {
		( { handleAdyenError } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' ) );
		emitError = jest.fn();
	} );

	it( 'ignores blank errors and the donor closing the Apple Pay sheet', () => {
		handleAdyenError( '', emitError, OTHER_WAYS_URL );
		handleAdyenError( null, emitError, OTHER_WAYS_URL );
		handleAdyenError( { error: '' }, emitError, OTHER_WAYS_URL );
		handleAdyenError( { name: 'CANCEL' }, emitError, OTHER_WAYS_URL );
		expect( emitError ).not.toHaveBeenCalled();
	} );

	it( 'leaves card field validation errors to the fields, except the one Adyen is investigating', () => {
		handleAdyenError( { error: 'error.va.gen.01' }, emitError, OTHER_WAYS_URL );
		expect( emitError ).not.toHaveBeenCalled();

		handleAdyenError( { error: 'error.va.sf-cc-num.02' }, emitError, OTHER_WAYS_URL );
		expect( emitError ).toHaveBeenCalledTimes( 1 );
		expect( emitError.mock.calls[ 0 ][ 0 ].error ).toMatch( /^Adyen error: error\.va\.sf-cc-num\.02 on / );
	} );

	it( 'shows the general error for any other error', () => {
		handleAdyenError( new Error( 'script blocked' ), emitError, OTHER_WAYS_URL );
		expect( emitError ).toHaveBeenCalledWith( {
			type: 'validation',
			messages: [ `donate_interface-error-msg-general:[${ OTHER_WAYS_URL }]` ]
		} );
	} );
} );
