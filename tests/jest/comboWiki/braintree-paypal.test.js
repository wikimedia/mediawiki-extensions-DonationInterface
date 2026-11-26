/* global describe it expect jest beforeEach */

const VueTestUtils = require( '@vue/test-utils' );
const { reactive } = require( 'vue' );
const BraintreePayPal = require( '../../../modules/ext.donationInterface.comboWiki/components/BraintreePayPal.vue' );
const { useAppState } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useAppState.js' );

const appState = useAppState();

// What the Braintree scripts define on window.braintree, see braintree-web paypal-checkout-v6
const session = { start: jest.fn() };
const paypalCheckout = {
	loadPayPalSDK: jest.fn(),
	createBillingAgreementSession: jest.fn(),
	createOneTimePaymentSession: jest.fn(),
	tokenizePayment: jest.fn()
};
window.braintree = {
	client: { create: jest.fn() },
	dataCollector: { create: jest.fn() },
	paypalCheckoutV6: { create: jest.fn() }
};

const TOKENIZED = {
	nonce: 'fake-nonce',
	details: { firstName: 'Pay', lastName: 'Pal', email: 'donor@example.org' }
};

function visit( queryString ) {
	window.history.replaceState( {}, '', '/index.php?title=Special:Donate&' + queryString );
}

/**
 * @param {Object} [props] Overrides for the donation and donorDetails props
 * @return {Object} The mounted component, once its setup in onMounted has finished
 */
async function mountPayPal( props = {} ) {
	const wrapper = VueTestUtils.mount( BraintreePayPal, {
		props: Object.assign( {
			donation: { frequency: 'once', amount: 10, currency: 'USD' },
			donorDetails: null
		}, props ),
		global: {
			config: {
				// paypal-button is defined by the PayPal SDK, so Vue can't resolve it here
				warnHandler: ( message ) => {
					if ( !message.includes( 'paypal-button' ) ) {
						// Any other Vue warning is still shown
						// eslint-disable-next-line no-console
						console.warn( message );
					}
				}
			},
			provide: {
				params: { braintreeConfiguration: { clientToken: 'client-token', scripts: [ 'client.js' ] } }
			}
		}
	} );
	await VueTestUtils.flushPromises();
	return wrapper;
}

/**
 * @return {Object} The options BraintreePayPal last passed to createBillingAgreementSession
 */
function billingAgreementOptions() {
	return paypalCheckout.createBillingAgreementSession.mock.lastCall[ 0 ];
}

/**
 * @return {Object} The options BraintreePayPal last passed to createOneTimePaymentSession
 */
function oneTimeOptions() {
	return paypalCheckout.createOneTimePaymentSession.mock.lastCall[ 0 ];
}

const MONTHLY = { donation: { frequency: 'month', amount: 3, currency: 'USD' } };

beforeEach( () => {
	window.mw.loader = { getScript: jest.fn( () => Promise.resolve() ) };
	window.braintree.client.create.mockResolvedValue( {} );
	window.braintree.dataCollector.create.mockResolvedValue( { deviceData: 'device-data' } );
	window.braintree.paypalCheckoutV6.create.mockResolvedValue( paypalCheckout );
	paypalCheckout.loadPayPalSDK.mockResolvedValue();
	paypalCheckout.createBillingAgreementSession.mockReturnValue( session );
	paypalCheckout.createOneTimePaymentSession.mockReturnValue( session );
	paypalCheckout.tokenizePayment.mockResolvedValue( TOKENIZED );
	appState.setLoading( false );
} );

describe( 'ComboWiki Braintree PayPal when the donor clicks the PayPal button', () => {
	beforeEach( () => {
		visit( 'country=US&currency=USD&uselang=en' );
	} );

	it( 'shows the PayPal button and doesn\'t go to PayPal by itself', async () => {
		const wrapper = await mountPayPal();
		expect( wrapper.find( 'paypal-button' ).isVisible() ).toBe( true );
		expect( session.start ).not.toHaveBeenCalled();
	} );

	it( 'opens a one-time PayPal checkout, not a billing agreement, for a one-time donation', async () => {
		const wrapper = await mountPayPal( { donation: { frequency: 'once', amount: 10.4, currency: 'USD' } } );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( paypalCheckout.createBillingAgreementSession ).not.toHaveBeenCalled();
		expect( oneTimeOptions() ).toEqual( expect.objectContaining( {
			amount: '10.4',
			currency: 'USD',
			intent: 'authorize'
		} ) );
		expect( session.start ).toHaveBeenCalledWith( { presentationMode: 'auto' } );
	} );

	it( 'opens a PayPal billing agreement for a monthly donation', async () => {
		const wrapper = await mountPayPal( MONTHLY );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( paypalCheckout.createOneTimePaymentSession ).not.toHaveBeenCalled();
		expect( billingAgreementOptions().billingAgreementDescription ).toBe( 'Wikimedia Foundation' );
		expect( session.start ).toHaveBeenCalledWith( { presentationMode: 'auto' } );
	} );

	it( 'makes the PayPal session before the click, so Safari doesn\'t block the popup', async () => {
		const wrapper = await mountPayPal();
		expect( paypalCheckout.createOneTimePaymentSession ).toHaveBeenCalledTimes( 1 );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( paypalCheckout.createOneTimePaymentSession ).toHaveBeenCalledTimes( 1 );
		expect( session.start ).toHaveBeenCalledWith( { presentationMode: 'auto' } );
	} );

	it( 'makes a billing agreement session when the donor changes to monthly', async () => {
		const wrapper = await mountPayPal();
		await wrapper.setProps( MONTHLY );
		expect( paypalCheckout.createBillingAgreementSession ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'uses the amount and frequency on the form when the donor clicks', async () => {
		const wrapper = await mountPayPal();
		await wrapper.setProps( { donation: { frequency: 'once', amount: 25, currency: 'USD' } } );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( oneTimeOptions().amount ).toBe( '25' );
	} );

	it( 'stops making sessions once unmounted, e.g. when the donor changes the payment method', async () => {
		const donation = reactive( { frequency: 'once', amount: 10, currency: 'USD' } );
		const wrapper = await mountPayPal( { donation } );
		wrapper.unmount();
		const callsBefore = paypalCheckout.createOneTimePaymentSession.mock.calls.length;
		donation.amount = 25;
		expect( paypalCheckout.createOneTimePaymentSession ).toHaveBeenCalledTimes( callsBefore );
	} );

	it( 'waits for the PayPal SDK before making a session for a change', async () => {
		let sdkLoaded;
		paypalCheckout.loadPayPalSDK.mockReturnValue( new Promise( ( resolve ) => {
			sdkLoaded = resolve;
		} ) );
		const donation = reactive( { frequency: 'once', amount: 10, currency: 'USD' } );
		const wrapper = await mountPayPal( { donation } );
		donation.amount = 25;
		expect( paypalCheckout.createOneTimePaymentSession ).not.toHaveBeenCalled();
		sdkLoaded();
		await VueTestUtils.flushPromises();
		expect( paypalCheckout.createOneTimePaymentSession ).toHaveBeenCalledTimes( 1 );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( oneTimeOptions().amount ).toBe( '25' );
	} );

	it( 'reports an error when PayPal won\'t make the session', async () => {
		const error = new Error( 'invalid options' );
		paypalCheckout.createOneTimePaymentSession.mockImplementationOnce( () => {
			throw error;
		} );
		const wrapper = await mountPayPal();
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( wrapper.emitted( 'error' ) ).toEqual( [ [ error ] ] );
	} );

	it( 'doesn\'t open PayPal while the donor details are invalid', async () => {
		const wrapper = await mountPayPal( { donorDetails: { validate: () => false } } );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		expect( session.start ).not.toHaveBeenCalled();
	} );

	it( 'submits a one-time donation with a nonce made from the order and payer', async () => {
		const wrapper = await mountPayPal();
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		// What PayPal passes to onApprove in the popup
		await oneTimeOptions().onApprove( { orderId: 'ORDER-1', payerId: 'PAYER1' } );
		expect( paypalCheckout.tokenizePayment ).toHaveBeenCalledWith( { orderId: 'ORDER-1', payerId: 'PAYER1' } );
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ expect.objectContaining( {
			payment_token: 'fake-nonce',
			device_data: 'device-data'
		} ) ] ] );
	} );

	it( 'submits a monthly donation with a nonce made from only the billing token', async () => {
		const wrapper = await mountPayPal( MONTHLY );
		await wrapper.find( 'paypal-button' ).trigger( 'click' );
		// What PayPal passes to onApprove in the popup
		await billingAgreementOptions().onApprove( { orderId: 'EC-1', billingToken: 'BA-1', payerId: 'PAYER1', fundingSource: 'paypal' } );
		expect( paypalCheckout.tokenizePayment ).toHaveBeenCalledWith( { billingToken: 'BA-1' } );
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ {
			payment_token: 'fake-nonce',
			device_data: 'device-data',
			first_name: 'Pay',
			last_name: 'Pal',
			email: 'donor@example.org'
		} ] ] );
	} );
} );

describe( 'ComboWiki Braintree PayPal when the donor chose PayPal before arriving', () => {
	const ZERO_CLICK = 'country=GB&currency=GBP&payment_method=paypal&recurring=0&uselang=en-gb&amount=10';

	it( 'goes straight to a one-time PayPal checkout', async () => {
		visit( ZERO_CLICK );
		const wrapper = await mountPayPal( { donation: { frequency: 'once', amount: 10, currency: 'GBP' } } );
		expect( paypalCheckout.createBillingAgreementSession ).not.toHaveBeenCalled();
		expect( oneTimeOptions() ).toEqual( expect.objectContaining( { amount: '10', currency: 'GBP' } ) );
		expect( session.start ).toHaveBeenCalledWith( { presentationMode: 'redirect' } );
		expect( appState.loading.value ).toBe( true );
		expect( wrapper.find( 'paypal-button' ).isVisible() ).toBe( false );
	} );

	it( 'asks PayPal to come back to the same page, or with paypalCancel on cancel', async () => {
		visit( ZERO_CLICK );
		await mountPayPal();
		const pageUrl = 'http://localhost/index.php?title=Special%3ADonate&' + ZERO_CLICK;
		expect( oneTimeOptions().returnUrl ).toBe( pageUrl );
		expect( oneTimeOptions().cancelUrl ).toBe( pageUrl + '&paypalCancel=1' );
	} );

	it( 'goes straight to a PayPal billing agreement for a monthly donation', async () => {
		visit( 'country=GB&currency=GBP&payment_method=paypal&recurring=1&frequency_unit=month&uselang=en-gb&amount=3' );
		await mountPayPal( MONTHLY );
		expect( paypalCheckout.createOneTimePaymentSession ).not.toHaveBeenCalled();
		expect( paypalCheckout.createBillingAgreementSession ).toHaveBeenCalled();
		expect( session.start ).toHaveBeenCalledWith( { presentationMode: 'redirect' } );
	} );

	it( 'doesn\'t go to PayPal without an amount', async () => {
		visit( 'country=GB&currency=GBP&payment_method=paypal&recurring=0&uselang=en-gb' );
		await mountPayPal( { donation: { frequency: 'once', amount: null } } );
		expect( session.start ).not.toHaveBeenCalled();
	} );

	it( 'doesn\'t go to PayPal while the donor details are invalid', async () => {
		visit( ZERO_CLICK );
		await mountPayPal( { donorDetails: { validate: () => false } } );
		expect( session.start ).not.toHaveBeenCalled();
	} );

	it( 'shows the PayPal button after the donor cancelled in PayPal', async () => {
		visit( ZERO_CLICK + '&paypalCancel=1&token=EC-1' );
		const wrapper = await mountPayPal();
		expect( session.start ).not.toHaveBeenCalled();
		expect( wrapper.find( 'paypal-button' ).isVisible() ).toBe( true );
		// paypalCancel stays, so a reload shows the button again
		expect( window.location.search ).toBe( '?title=Special%3ADonate&' + ZERO_CLICK + '&paypalCancel=1' );
	} );
} );

describe( 'ComboWiki Braintree PayPal when PayPal sends the donor back from a one-time checkout', () => {
	const RETURN = 'country=GB&currency=GBP&payment_method=paypal&recurring=0&uselang=en-gb&amount=10&token=ORDER-1&PayerID=PAYER1';

	beforeEach( () => {
		visit( RETURN );
	} );

	it( 'submits with a nonce made from the order and payer in the URL', async () => {
		const wrapper = await mountPayPal();
		expect( session.start ).not.toHaveBeenCalled();
		expect( paypalCheckout.tokenizePayment ).toHaveBeenCalledWith( { orderId: 'ORDER-1', payerId: 'PAYER1' } );
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ expect.objectContaining( {
			payment_token: 'fake-nonce'
		} ) ] ] );
	} );

	it( 'removes the order and payer from the URL, so a reload can\'t submit them again', async () => {
		await mountPayPal();
		expect( window.location.search ).not.toContain( 'token' );
		expect( window.location.search ).not.toContain( 'PayerID' );
	} );
} );

describe( 'ComboWiki Braintree PayPal when PayPal sends the donor back from a billing agreement', () => {
	const RETURN = 'country=GB&currency=GBP&payment_method=paypal&recurring=1&frequency_unit=month&uselang=en-gb&amount=3&token=EC-1&ba_token=BA-1';

	beforeEach( () => {
		visit( RETURN );
	} );

	it( 'submits with a nonce made from the billing token in the URL', async () => {
		const wrapper = await mountPayPal();
		expect( session.start ).not.toHaveBeenCalled();
		expect( paypalCheckout.tokenizePayment ).toHaveBeenCalledWith( { billingToken: 'BA-1' } );
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ expect.objectContaining( {
			payment_token: 'fake-nonce',
			device_data: 'device-data'
		} ) ] ] );
	} );

	it( 'removes the tokens from the URL, so a reload can\'t submit them again', async () => {
		await mountPayPal();
		expect( window.location.search ).not.toContain( 'token' );
	} );

	it( 'reports an error when the billing token can\'t be tokenized', async () => {
		const error = new Error( 'tokenize failed' );
		paypalCheckout.tokenizePayment.mockRejectedValue( error );
		const wrapper = await mountPayPal();
		expect( wrapper.emitted( 'submit' ) ).toBeUndefined();
		expect( wrapper.emitted( 'error' ) ).toEqual( [ [ error ] ] );
	} );
} );
