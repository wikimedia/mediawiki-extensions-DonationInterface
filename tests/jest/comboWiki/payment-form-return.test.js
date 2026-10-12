/* global describe it expect beforeEach */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const RecurringConvert = require( '../../../modules/ext.donationInterface.comboWiki/components/RecurringConvert.vue' );
const { useAppState } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useAppState.js' );

// useAppState() hands back the same refs PaymentForm writes to.
const appState = useAppState();

const THANK_YOU_URL = 'https://thankyou.example.org/wiki/Thank_You/en?amount=10.00&order_id=1335.9';

// Params for a normal visit to Special:Donate
const NEW_DONATION_PARAMS = {
	amount: '0.00',
	country: 'US',
	currency: 'USD',
	order_id: '1336.1',
	payment_method: 'cc',
	gateway: 'gravy',
	language: 'en',
	monthlyConvertReturn: false,
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1 }
};

// Params when DonateGatewayResult sends the donor back to Special:Donate after a
// completed one-time donation that qualifies for monthly convert
const MONTHLY_CONVERT_RETURN_PARAMS = Object.assign( {}, NEW_DONATION_PARAMS, {
	amount: '10.00',
	order_id: '1335.9',
	monthlyConvertReturn: true,
	DonationInterfaceThankYouPage: THANK_YOU_URL
} );

function visit( queryString ) {
	window.history.replaceState( {}, '', '/index.php?' + queryString );
}

async function mountForm( params ) {
	const wrapper = VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: { params },
			stubs: { 'sms-optin': true }
		}
	} );
	// mounted() opens the modal, which renders on the next tick
	await VueTestUtils.flushPromises();
	return wrapper;
}

describe( 'ComboWiki return after a donation that qualifies for monthly convert', () => {
	beforeEach( () => {
		// useAppState.js holds its refs at module scope, so reset them by hand
		appState.setShowRecurringConvert( false );
		appState.clearError();
		visit( 'title=Special:Donate&monthlyConvert=1&order_id=1335.9&gateway=gravy&uselang=en&country=US' );
	} );

	it( 'opens the monthly convert modal', async () => {
		await mountForm( MONTHLY_CONVERT_RETURN_PARAMS );
		expect( appState.showRecurringConvert.value ).toBe( true );
	} );

	it( 'offers to convert the donation the donor just made', async () => {
		const modal = ( await mountForm( MONTHLY_CONVERT_RETURN_PARAMS ) ).findComponent( RecurringConvert );
		expect( modal.props( 'donation' ) ).toMatchObject( {
			amount: '10.00',
			currency: 'USD',
			country: 'US',
			paymentMethod: 'cc',
			gateway: 'gravy'
		} );
		expect( modal.props( 'chargedAmount' ) ).toBe( 10 );
		expect( modal.props( 'thankYouUrl' ) ).toBe( THANK_YOU_URL );
	} );

	it( 'shows the donor their form without the payment methods', async () => {
		const wrapper = await mountForm( MONTHLY_CONVERT_RETURN_PARAMS );
		expect( wrapper.text() ).toContain( 'combowiki-intro-heading' );
		expect( wrapper.findComponent( PaymentMethodForm ).exists() ).toBe( false );
	} );

	it( 'leaves a normal visit to start a new donation', async () => {
		visit( 'title=Special:Donate&uselang=en&country=US' );
		const wrapper = await mountForm( NEW_DONATION_PARAMS );
		expect( appState.showRecurringConvert.value ).toBe( false );
		expect( wrapper.findComponent( PaymentMethodForm ).exists() ).toBe( true );
	} );
} );

describe( 'ComboWiki return after a payment that failed', () => {
	beforeEach( () => {
		appState.setShowRecurringConvert( false );
		appState.clearError();
		visit( 'title=Special:Donate&paymentFailed=1&uselang=en&country=US' );
	} );

	it( 'shows the payment failed error', async () => {
		await mountForm( NEW_DONATION_PARAMS );
		expect( appState.error.value ).toBe( 'combowiki-payment-failed' );
	} );

	it( 'removes the flag from the address, so a reload or Try Again gives a clean form', async () => {
		await mountForm( NEW_DONATION_PARAMS );
		const query = new URLSearchParams( window.location.search );
		expect( query.has( 'paymentFailed' ) ).toBe( false );
		expect( query.get( 'uselang' ) ).toBe( 'en' );
		expect( query.get( 'country' ) ).toBe( 'US' );
	} );

	it( 'shows no error on a normal visit', async () => {
		visit( 'title=Special:Donate&uselang=en&country=US' );
		await mountForm( NEW_DONATION_PARAMS );
		expect( appState.error.value ).toBeNull();
	} );
} );
