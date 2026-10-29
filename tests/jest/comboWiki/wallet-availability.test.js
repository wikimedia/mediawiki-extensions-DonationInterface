/* global describe it expect jest beforeEach */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => ( {
	loadScript: jest.fn()
} ) );

const { loadScript } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );
const { isApplePayAvailable, isGooglePayAvailable } = require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );

const GRAVY_CONFIGURATION = {
	appleScript: 'apple-pay-sdk.js',
	googleScript: 'pay.js',
	googleEnvironment: 'TEST',
	googleAllowedNetworks: [ 'VISA', 'MASTERCARD' ]
};

beforeEach( () => {
	window.mw.log = { error: jest.fn() };
	loadScript.mockReset();
} );

describe( 'ComboWiki Apple Pay availability', () => {
	beforeEach( () => {
		delete window.ApplePaySession;
	} );

	it( 'is unavailable when Apple\'s SDK fails to load', async () => {
		loadScript.mockRejectedValue( new Error( 'blocked' ) );
		expect( await isApplePayAvailable( GRAVY_CONFIGURATION ) ).toBe( false );
		expect( loadScript ).toHaveBeenCalledWith( 'apple-pay-sdk.js' );
	} );

	it( 'is unavailable when the SDK loads but the browser has no ApplePaySession', async () => {
		loadScript.mockResolvedValue();
		expect( await isApplePayAvailable( GRAVY_CONFIGURATION ) ).toBe( false );
	} );

	it( 'is available once the SDK defines ApplePaySession', async () => {
		loadScript.mockImplementation( () => {
			window.ApplePaySession = function () {};
			window.ApplePaySession.canMakePayments = () => true;
			return Promise.resolve();
		} );
		expect( await isApplePayAvailable( GRAVY_CONFIGURATION ) ).toBe( true );
	} );
} );

// The Google Pay client and an answered readiness check are kept for the page, so
// these run in order: the cases that leave nothing kept first.
describe( 'ComboWiki Google Pay availability', () => {
	const isReadyToPay = jest.fn();
	window.google = { payments: { api: { PaymentsClient: jest.fn( () => ( { isReadyToPay } ) ) } } };

	beforeEach( () => {
		isReadyToPay.mockReset();
	} );

	it( 'is unavailable when pay.js fails to load', async () => {
		loadScript.mockRejectedValue( new Error( 'blocked' ) );
		expect( await isGooglePayAvailable( GRAVY_CONFIGURATION ) ).toBe( false );
		expect( loadScript ).toHaveBeenCalledWith( 'pay.js' );
	} );

	it( 'is unavailable when the readiness check rejects, and asks again next time', async () => {
		loadScript.mockResolvedValue();
		isReadyToPay.mockRejectedValueOnce( new Error( 'third-party cookies blocked' ) );
		expect( await isGooglePayAvailable( GRAVY_CONFIGURATION ) ).toBe( false );

		isReadyToPay.mockResolvedValueOnce( { result: false } );
		expect( await isGooglePayAvailable( GRAVY_CONFIGURATION ) ).toBe( false );
		expect( isReadyToPay ).toHaveBeenCalledTimes( 2 );
	} );
} );

describe( 'ComboWiki Google Pay availability once answered', () => {
	it( 'is available when Google says the donor can pay, asking only once', async () => {
		jest.resetModules();
		jest.doMock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => ( {
			loadScript: jest.fn( () => Promise.resolve() )
		} ) );
		const isReadyToPay = jest.fn().mockResolvedValue( { result: true } );
		window.google = { payments: { api: { PaymentsClient: jest.fn( () => ( { isReadyToPay } ) ) } } };
		const freshAvailability = require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );

		expect( await freshAvailability.isGooglePayAvailable( GRAVY_CONFIGURATION ) ).toBe( true );
		expect( await freshAvailability.isGooglePayAvailable( GRAVY_CONFIGURATION ) ).toBe( true );
		expect( isReadyToPay ).toHaveBeenCalledTimes( 1 );
		expect( isReadyToPay.mock.calls[ 0 ][ 0 ].allowedPaymentMethods[ 0 ].parameters.allowedCardNetworks )
			.toEqual( [ 'VISA', 'MASTERCARD' ] );
	} );
} );

// An answered readiness check is kept for the page, so each case loads a fresh copy of the module.
describe( 'ComboWiki Adyen Apple Pay availability', () => {
	const ADYEN_CONFIGURATION = {
		script: { src: 'adyen.js' },
		locale: 'en-US',
		paymentMethodsResponse: { paymentMethods: [ { type: 'scheme' }, { type: 'applepay' } ] }
	};
	const DONATION = { amount: 10, currency: 'USD', country: 'US' };
	const APPLE_PAY_CONFIGURATION = { buttonType: 'donate' };
	let getAdyenCheckout, isAvailable, ApplePay;

	function loadFreshAvailability() {
		jest.resetModules();
		getAdyenCheckout = jest.fn( () => Promise.resolve( { checkout: true } ) );
		jest.doMock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => ( {
			loadScript: jest.fn(),
			getAdyenCheckout,
			getAdyenApplePayConfiguration: jest.fn( () => APPLE_PAY_CONFIGURATION )
		} ) );
		return require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );
	}

	beforeEach( () => {
		isAvailable = jest.fn( () => Promise.resolve() );
		ApplePay = jest.fn( () => ( { isAvailable } ) );
		window.AdyenWeb = { ApplePay };
	} );

	it( 'is unavailable without an Adyen configuration', async () => {
		const { isAdyenApplePayAvailable } = loadFreshAvailability();
		expect( await isAdyenApplePayAvailable( undefined, DONATION ) ).toBe( false );
		expect( getAdyenCheckout ).not.toHaveBeenCalled();
	} );

	it( 'is unavailable, without loading Adyen, when Adyen didn\'t offer Apple Pay', async () => {
		const { isAdyenApplePayAvailable } = loadFreshAvailability();
		const configuration = Object.assign( {}, ADYEN_CONFIGURATION, {
			paymentMethodsResponse: { paymentMethods: [ { type: 'scheme' } ] }
		} );
		expect( await isAdyenApplePayAvailable( configuration, DONATION ) ).toBe( false );
		expect( getAdyenCheckout ).not.toHaveBeenCalled();
	} );

	it( 'is unavailable when Adyen\'s SDK fails to load, and asks again next time', async () => {
		const { isAdyenApplePayAvailable } = loadFreshAvailability();
		getAdyenCheckout.mockRejectedValueOnce( new Error( 'blocked' ) );
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( false );
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( true );
		expect( getAdyenCheckout ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'is unavailable when the component\'s isAvailable() rejects, and asks again next time', async () => {
		const { isAdyenApplePayAvailable } = loadFreshAvailability();
		isAvailable.mockRejectedValueOnce( new Error( 'Apple Pay is not available on this device' ) );
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( false );
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( true );
		expect( isAvailable ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'is available when the component says so, asking only once', async () => {
		const { isAdyenApplePayAvailable } = loadFreshAvailability();
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( true );
		expect( await isAdyenApplePayAvailable( ADYEN_CONFIGURATION, DONATION ) ).toBe( true );

		expect( isAvailable ).toHaveBeenCalledTimes( 1 );
		expect( getAdyenCheckout ).toHaveBeenCalledWith( ADYEN_CONFIGURATION, 'US' );
		expect( ApplePay ).toHaveBeenCalledWith( { checkout: true }, APPLE_PAY_CONFIGURATION );
	} );
} );
