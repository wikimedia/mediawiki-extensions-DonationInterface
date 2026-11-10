/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );

// Params as init.js provides them for ?country=US
const PARAMS = {
	amount: '0.00',
	country: 'US',
	currency: 'USD',
	frequency_unit: '',
	order_id: '20037.1',
	payment_method: 'cc',
	recurring: '',
	language: 'en',
	gateway: 'gravy',
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1 }
};

/**
 * @param {Object} params Replaces some of PARAMS
 * @return {string} The frequency the form starts on
 */
const startingFrequency = ( params ) => VueTestUtils.shallowMount( PaymentForm, {
	global: {
		provide: { params: Object.assign( {}, PARAMS, params ) },
		stubs: { 'sms-optin': true }
	}
} ).vm.donation.frequency;

describe( 'ComboWiki payment form starting frequency', () => {
	it( 'starts on one-time when Special:Donate sends no frequency unit', () => {
		// Donate.php sends '' when the URL has none
		expect( startingFrequency( { frequency_unit: '' } ) ).toBe( 'once' );
	} );

	it( 'starts on one-time when the params have no frequency unit at all', () => {
		expect( startingFrequency( { frequency_unit: undefined } ) ).toBe( 'once' );
	} );

	it.each( [ 'month', 'year' ] )( 'starts on the %s frequency from the URL', ( frequencyUnit ) => {
		expect( startingFrequency( { recurring: '1', frequency_unit: frequencyUnit } ) ).toBe( frequencyUnit );
	} );
} );
