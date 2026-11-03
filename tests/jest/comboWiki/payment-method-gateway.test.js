/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );
const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );

// The server sends the payment methods for the country with the gateway that handles each one
const CARD_ON_ADYEN = [
	{ method: 'cc', gateway: 'adyen' },
	{ method: 'paypal', gateway: 'gravy' }
];
const CARD_ON_GRAVY = [
	{ method: 'cc', gateway: 'gravy' },
	{ method: 'paypal', gateway: 'gravy' }
];
const VENMO_ON_BRAINTREE = [
	{ method: 'venmo', gateway: 'braintree' }
];
const VENMO_ON_GRAVY = [
	{ method: 'venmo', gateway: 'gravy' }
];
function mountMethodForm( paymentMethods ) {
	return VueTestUtils.shallowMount( PaymentMethodForm, {
		props: {
			donation: { frequency: 'once', amount: 10, currency: 'USD', country: 'US', paymentMethod: '' }
		},
		global: { provide: { params: { gateway: 'gravy', paymentMethods } } }
	} );
}

function mountPaymentForm( paymentMethods ) {
	return VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: {
				params: {
					amount: '0.00',
					country: 'US',
					currency: 'USD',
					order_id: '1336.1',
					payment_method: 'cc',
					gateway: 'gravy',
					paymentMethods,
					language: 'en',
					wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
					wgDonationInterfaceCurrencyRates: { USD: 1 }
				}
			},
			stubs: { 'sms-optin': true }
		}
	} );
}

describe( 'ComboWiki payment methods from more than one gateway', () => {
	it( 'offers the Adyen card form when the server sends cards to Adyen, next to Gravy PayPal', () => {
		const availablePaymentMethods = mountMethodForm( CARD_ON_ADYEN ).vm.availablePaymentMethods;
		expect( availablePaymentMethods ).toEqual( [ 'adyen_card', 'paypal' ] );
	} );

	it( 'offers the Gravy card form when the server sends cards to Gravy', () => {
		const availablePaymentMethods = mountMethodForm( CARD_ON_GRAVY ).vm.availablePaymentMethods;
		expect( availablePaymentMethods ).toEqual( [ 'card', 'paypal' ] );
	} );

	it( 'offers the Braintree Venmo form when the server sends Venmo to Braintree', () => {
		const availablePaymentMethods = mountMethodForm( VENMO_ON_BRAINTREE ).vm.availablePaymentMethods;
		expect( availablePaymentMethods ).toEqual( [ 'braintree_venmo' ] );
	} );

	it( 'offers the Gravy Venmo form when the server sends Venmo via Gravy', () => {
		const availablePaymentMethods = mountMethodForm( VENMO_ON_GRAVY ).vm.availablePaymentMethods;
		expect( availablePaymentMethods ).toEqual( [ 'venmo' ] );
	} );
} );

describe( 'ComboWiki payment method preselected from the URL', () => {
	it( 'selects the card form of the gateway the server sends cards to', () => {
		expect( mountPaymentForm( CARD_ON_ADYEN ).vm.donation.paymentMethod ).toBe( 'adyen_card' );
		expect( mountPaymentForm( CARD_ON_GRAVY ).vm.donation.paymentMethod ).toBe( 'card' );
	} );

	it( 'selects nothing when the server does not offer the method', () => {
		expect( mountPaymentForm( [ { method: 'paypal', gateway: 'gravy' } ] ).vm.donation.paymentMethod ).toBeNull();
	} );
} );
