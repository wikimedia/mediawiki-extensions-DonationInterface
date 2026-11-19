/* global describe it expect jest beforeEach afterEach */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js', () => ( {
	getGooglePaymentsClient: jest.fn(),
	isApplePayAvailable: jest.fn(),
	isGooglePayAvailable: jest.fn()
} ) );
jest.mock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => Object.assign(
	{},
	jest.requireActual( '../../../modules/ext.donationInterface.comboWiki/utils.js' ),
	{
		getAdyenCheckout: jest.fn(),
		getAdyenApplePayConfiguration: jest.fn( () => ( {} ) ),
		amountInMinorUnits: jest.fn( () => 1000 )
	}
) );

const VueTestUtils = require( '@vue/test-utils' );
const { getGooglePaymentsClient, isApplePayAvailable, isGooglePayAvailable } = require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );
const { getAdyenCheckout } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const ApplePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/ApplePayComponent.vue' );
const AdyenApplePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/AdyenApplePayComponent.vue' );
const GooglePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/GooglePayComponent.vue' );
const BraintreePayPal = require( '../../../modules/ext.donationInterface.comboWiki/components/BraintreePayPal.vue' );
const BraintreeVenmo = require( '../../../modules/ext.donationInterface.comboWiki/components/BraintreeVenmo.vue' );

// Gravy Apple Pay and Google Pay, which wait for their check and their SDK,
// and card and Gravy PayPal, which draw their button straight away
const GRAVY_METHODS = [
	{ method: 'apple', gateway: 'gravy' },
	{ method: 'google', gateway: 'gravy' },
	{ method: 'paypal', gateway: 'gravy' },
	{ method: 'cc', gateway: 'gravy' }
];

function mountForm( { paymentMethods = GRAVY_METHODS, appleFormFields = {} } = {} ) {
	return VueTestUtils.shallowMount( PaymentMethodForm, {
		props: {
			donation: { frequency: 'once', amount: 10, currency: 'USD', country: 'US', paymentMethod: '' }
		},
		global: {
			provide: {
				params: {
					gateway: 'gravy',
					paymentMethods,
					// Apple Pay has form fields in e.g. AU (state), so it's offered in the form section
					DonationInterfaceFormFields: { method: { apple: appleFormFields, cc: { first_name: true } } }
				}
			}
		}
	} );
}

const buttonsRow = ( wrapper ) => wrapper.find( '.combo-wiki__payment-method-buttons' );
const isHidden = ( wrapper ) => buttonsRow( wrapper ).classes( 'combo-wiki__payment-method-buttons--loading' );
// Browsers have an inert property, which Vue sets. jsdom doesn't, so Vue sets the attribute to "true" or "false".
const isInert = ( wrapper ) => buttonsRow( wrapper ).attributes( 'inert' ) === 'true';

/**
 * What a wallet component emits once its SDK has drawn its button
 *
 * @param {Object} wrapper The mounted PaymentMethodForm
 * @param {Object} component The wallet component
 */
async function emitReady( wrapper, component ) {
	wrapper.findComponent( component ).vm.$emit( 'ready' );
	await VueTestUtils.flushPromises();
}

describe( 'ComboWiki one-click payment buttons shown together', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		isApplePayAvailable.mockResolvedValue( true );
		isGooglePayAvailable.mockResolvedValue( true );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'hides the buttons while a wallet\'s availability check is running', async () => {
		isApplePayAvailable.mockReturnValue( new Promise( () => {} ) );
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();
		await emitReady( wrapper, GooglePayComponent );

		expect( isHidden( wrapper ) ).toBe( true );
		expect( buttonsRow( wrapper ).attributes( 'aria-busy' ) ).toBe( 'true' );
		expect( isInert( wrapper ) ).toBe( true );
	} );

	it( 'hides the buttons until every wallet in the row has drawn its button', async () => {
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();
		// The wallets are mounted, so their SDKs can draw into the hidden row
		expect( wrapper.findComponent( ApplePayComponent ).exists() ).toBe( true );
		expect( isHidden( wrapper ) ).toBe( true );

		await emitReady( wrapper, GooglePayComponent );
		expect( isHidden( wrapper ) ).toBe( true );

		await emitReady( wrapper, ApplePayComponent );
		expect( isHidden( wrapper ) ).toBe( false );
		expect( buttonsRow( wrapper ).attributes( 'aria-busy' ) ).toBe( 'false' );
		expect( isInert( wrapper ) ).toBe( false );
	} );

	it( 'stops waiting for a wallet that reports an error, and still shows the donor the error', async () => {
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();
		await emitReady( wrapper, GooglePayComponent );

		const error = { type: 'validation', messages: [ 'Apple Pay is not available.' ] };
		wrapper.findComponent( ApplePayComponent ).vm.$emit( 'error', error );
		await VueTestUtils.flushPromises();

		expect( isHidden( wrapper ) ).toBe( false );
		expect( wrapper.emitted( 'donationError' ) ).toEqual( [ [ error ] ] );
	} );

	it( 'doesn\'t wait for a wallet the browser can\'t use', async () => {
		isGooglePayAvailable.mockResolvedValue( false );
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();
		await emitReady( wrapper, ApplePayComponent );

		expect( isHidden( wrapper ) ).toBe( false );
	} );

	it( 'doesn\'t wait for a wallet offered in the form section rather than the row', async () => {
		const wrapper = mountForm( { appleFormFields: { state_province: true } } );
		await VueTestUtils.flushPromises();
		await emitReady( wrapper, GooglePayComponent );

		expect( isHidden( wrapper ) ).toBe( false );
	} );

	it( 'shows the buttons straight away when none of them has to load', () => {
		const wrapper = mountForm( { paymentMethods: [
			{ method: 'paypal', gateway: 'gravy' },
			{ method: 'cc', gateway: 'gravy' }
		] } );

		expect( isHidden( wrapper ) ).toBe( false );
	} );

	it( 'shows the buttons anyway when a wallet takes too long, and keeps them shown', async () => {
		let answerApplePay;
		isApplePayAvailable.mockReturnValue( new Promise( ( resolve ) => {
			answerApplePay = resolve;
		} ) );
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();

		jest.advanceTimersByTime( 2999 );
		await VueTestUtils.flushPromises();
		expect( isHidden( wrapper ) ).toBe( true );

		jest.advanceTimersByTime( 1 );
		await VueTestUtils.flushPromises();
		expect( isHidden( wrapper ) ).toBe( false );

		// Apple Pay turns up late and hasn't drawn its button yet, nor has Google Pay:
		// they appear as they load rather than hiding the others again
		answerApplePay( true );
		await VueTestUtils.flushPromises();
		expect( wrapper.findComponent( ApplePayComponent ).exists() ).toBe( true );
		expect( isHidden( wrapper ) ).toBe( false );
	} );
} );

// Each wallet tells PaymentMethodForm when its button is drawn
describe( 'ComboWiki wallet buttons that report ready', () => {
	const donation = { frequency: 'once', amount: 10, currency: 'USD', country: 'US' };

	function mountWallet( component, params, props = {}, options = {} ) {
		return VueTestUtils.mount( component, Object.assign( {
			props: Object.assign( { donation }, props ),
			global: {
				config: {
					// Web components from the wallet SDKs, which Vue can't resolve here
					warnHandler: () => {}
				},
				provide: { params }
			}
		}, options ) );
	}

	beforeEach( () => {
		window.mw.log = { error: jest.fn() };
		window.mw.loader = { getScript: jest.fn( () => Promise.resolve() ) };
		window.braintree = {
			client: { create: jest.fn().mockResolvedValue( {} ) },
			dataCollector: { create: jest.fn().mockResolvedValue( { deviceData: 'device-data' } ) },
			paypalCheckoutV6: { create: jest.fn().mockResolvedValue( {
				loadPayPalSDK: jest.fn().mockResolvedValue(),
				createBillingAgreementSession: jest.fn( () => ( { start: jest.fn() } ) )
			} ) },
			venmo: { create: jest.fn().mockResolvedValue( {} ) }
		};
		window.history.replaceState( {}, '', '/index.php?title=Special:Donate&country=US' );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	/**
	 * Draws PayPal's button as its SDK does at first: the label without the Donate text,
	 * which it adds once it has fetched the translation
	 *
	 * @param {Object} wrapper The mounted BraintreePayPal
	 * @return {Element} The label's text element
	 */
	function drawPayPalButtonWithoutText( wrapper ) {
		const shadow = wrapper.find( 'paypal-button' ).element.attachShadow( { mode: 'open' } );
		shadow.innerHTML = '<button><svg class="label"><text></text></svg></button>';
		return shadow.querySelector( 'text' );
	}

	it( 'Braintree PayPal waits for the Donate text before it reports ready', async () => {
		const wrapper = mountWallet( BraintreePayPal, { braintreeConfiguration: {} } );
		const text = drawPayPalButtonWithoutText( wrapper );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toBeUndefined();

		text.textContent = ' Donate';
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );

	it( 'Braintree PayPal reports ready after 1s if the Donate text never comes', async () => {
		jest.useFakeTimers();
		const wrapper = mountWallet( BraintreePayPal, { braintreeConfiguration: {} } );
		drawPayPalButtonWithoutText( wrapper );
		await VueTestUtils.flushPromises();

		jest.advanceTimersByTime( 999 );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toBeUndefined();

		jest.advanceTimersByTime( 1 );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );

	it( 'Braintree PayPal reports ready once the PayPal SDK has loaded', async () => {
		const wrapper = mountWallet( BraintreePayPal, { braintreeConfiguration: { scripts: [ 'client.js' ] } } );
		expect( wrapper.emitted( 'ready' ) ).toBeUndefined();
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );

	it( 'Braintree PayPal reports an error, not ready, when the PayPal SDK fails to load', async () => {
		window.braintree.paypalCheckoutV6.create.mockRejectedValue( new Error( 'blocked' ) );
		const wrapper = mountWallet( BraintreePayPal, { braintreeConfiguration: {} } );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toBeUndefined();
		expect( wrapper.emitted( 'error' ) ).toHaveLength( 1 );
	} );

	it( 'Braintree Venmo reports ready once Venmo is set up', async () => {
		const wrapper = mountWallet( BraintreeVenmo, { braintreeConfiguration: {} } );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );

	it( 'Gravy Apple Pay reports ready once it shows its button', async () => {
		isApplePayAvailable.mockResolvedValue( true );
		// Whether the button is in the DOM at the moment ready is emitted
		let wrapper = null;
		const onReady = jest.fn( () => wrapper.find( 'apple-pay-button' ).exists() );
		wrapper = mountWallet( ApplePayComponent, { gravyConfiguration: {} }, { onReady } );
		await VueTestUtils.flushPromises();
		expect( onReady ).toHaveBeenCalledTimes( 1 );
		expect( onReady ).toHaveReturnedWith( true );
	} );

	it( 'Adyen Apple Pay reports ready once it mounts its button', async () => {
		const mount = jest.fn();
		getAdyenCheckout.mockResolvedValue( {} );
		window.AdyenWeb = { ApplePay: jest.fn( () => ( { isAvailable: () => Promise.resolve(), mount } ) ) };
		const wrapper = mountWallet( AdyenApplePayComponent, { adyenConfiguration: {} } );
		await VueTestUtils.flushPromises();
		expect( mount ).toHaveBeenCalledWith( '#applepay-container' );
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );

	it( 'Google Pay reports ready once it draws its button', async () => {
		isGooglePayAvailable.mockResolvedValue( true );
		getGooglePaymentsClient.mockReturnValue( { createButton: () => document.createElement( 'button' ) } );
		window.mw.util = Object.assign( {}, window.mw.util, { debounce: ( fn ) => fn } );
		// It draws into #googlepay-container, so it has to be in the document
		const wrapper = mountWallet(
			GooglePayComponent,
			{ gravyConfiguration: {} },
			{ paymentMethod: 'googlepay' },
			{ attachTo: document.body }
		);
		await VueTestUtils.flushPromises();
		expect( wrapper.find( 'button' ).exists() ).toBe( true );
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
		wrapper.unmount();
	} );
} );
