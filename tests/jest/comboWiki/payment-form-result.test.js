/* global describe it expect jest beforeEach afterAll */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const { useAppState } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useAppState.js' );

// useAppState() hands back the same refs PaymentForm writes to.
const appState = useAppState();

const THANK_YOU_URL = 'https://thankyou.example.org/wiki/Thank_You/en?amount=10.00&order_id=1336.1';

// Params for a normal visit to Special:Donate
const PARAMS = {
	amount: '0.00',
	country: 'US',
	currency: 'USD',
	order_id: '1336.1',
	payment_method: 'cc',
	gateway: 'adyen',
	paymentMethods: [ { method: 'cc', gateway: 'adyen' } ],
	language: 'en',
	monthlyConvertReturn: false,
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1 }
};

// jsdom can't navigate, so the redirect itself is replaced
const redirectTargetUrl = jest.spyOn( PaymentForm.methods, 'redirectTargetUrl' ).mockImplementation( () => {} );

afterAll( () => {
	redirectTargetUrl.mockRestore();
} );

/**
 * Mounts the form and has PaymentMethodForm report a successful API result for a donation.
 *
 * @param {string} frequency
 * @param {Object} response The API's result
 * @return {Promise<Object>}
 */
const donationSucceeds = async ( frequency, response ) => {
	const wrapper = VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: { params: PARAMS },
			stubs: { 'sms-optin': true }
		}
	} );
	await wrapper.setData( { donation: { frequency } } );
	wrapper.findComponent( PaymentMethodForm ).vm.$emit( 'donationSuccess', {
		result: Object.assign( { isFailed: false, thankYouPage: THANK_YOU_URL }, response )
	} );
	return wrapper;
};

describe( 'ComboWiki payment form after a successful donation', () => {
	beforeEach( () => {
		// useAppState.js holds its refs at module scope, so reset them by hand
		appState.setShowRecurringConvert( false );
	} );

	it( 'leaves the redirect to the payment method when the API sends form data to post', async () => {
		// e.g. AdyenCardForm posting to the card issuer's 3D Secure page
		await donationSucceeds( 'monthly', {
			redirect: 'https://bank.example/3ds',
			formData: { PaReq: 'PAREQ', MD: 'MD', TermUrl: 'https://donate.example/return' }
		} );
		expect( redirectTargetUrl ).not.toHaveBeenCalled();
	} );

	it.each( [
		[ 'no form data', {} ],
		[ 'form data as an empty array', { formData: [] } ],
		[ 'empty form data', { formData: {} } ],
		[ 'null form data', { formData: null } ]
	] )( 'redirects the donor when the API sends %s', async ( label, response ) => {
		await donationSucceeds( 'monthly', Object.assign( { redirect: 'https://thankyou.wiki' }, response ) );
		expect( redirectTargetUrl ).toHaveBeenCalledWith( 'https://thankyou.wiki' );
	} );

	it( 'offers monthly convert instead of redirecting a one-time donation with no redirect', async () => {
		await donationSucceeds( 'once', { formData: [] } );
		expect( redirectTargetUrl ).not.toHaveBeenCalled();
		expect( appState.showRecurringConvert.value ).toBe( true );
	} );
} );
