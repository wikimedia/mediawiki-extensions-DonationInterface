/* global describe it expect jest beforeEach */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js', () => ( {
	isApplePayAvailable: jest.fn(),
	isGooglePayAvailable: jest.fn()
} ) );

const VueTestUtils = require( '@vue/test-utils' );
const { isApplePayAvailable, isGooglePayAvailable } = require( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const ApplePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/ApplePayComponent.vue' );
const GooglePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/GooglePayComponent.vue' );
const GravyCardForm = require( '../../../modules/ext.donationInterface.comboWiki/components/GravyCardForm.vue' );

function mountForm( { appleFormFields = {}, paymentMethod = '', paymentMethods = null } = {} ) {
	const params = {
		gateway: 'gravy',
		paymentMethods: [
			{ method: 'apple', gateway: 'gravy' },
			{ method: 'google', gateway: 'gravy' },
			{ method: 'paypal', gateway: 'gravy' },
			{ method: 'cc', gateway: 'gravy' }
		],
		// Apple Pay has form fields in e.g. AU (state), so it's offered in the form section
		DonationInterfaceFormFields: { method: { apple: appleFormFields, cc: { first_name: true } } }
	};
	if ( paymentMethods ) {
		params.paymentMethods = paymentMethods;
	}
	return VueTestUtils.shallowMount( PaymentMethodForm, {
		props: {
			donation: { frequency: 'once', amount: 10, currency: 'USD', country: 'US', paymentMethod }
		},
		global: { provide: { params } }
	} );
}

const AU_APPLE_FIELDS = { state_province: true };

describe( 'ComboWiki wallets the browser may not support', () => {
	beforeEach( () => {
		isApplePayAvailable.mockResolvedValue( true );
		isGooglePayAvailable.mockResolvedValue( true );
	} );

	it( 'hides a wallet until its check says the browser can use it', async () => {
		isApplePayAvailable.mockReturnValue( new Promise( () => {} ) );
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();

		expect( wrapper.vm.availablePaymentMethods ).not.toContain( 'applepay' );
		expect( wrapper.vm.availablePaymentMethods ).toContain( 'googlepay' );
	} );

	it( 'offers the wallets the browser can use', async () => {
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();
		expect( wrapper.vm.availablePaymentMethods ).toEqual( [ 'card', 'paypal', 'applepay', 'googlepay' ] );
	} );

	it( 'never offers a wallet the browser can\'t use, nor shows an error', async () => {
		isGooglePayAvailable.mockResolvedValue( false );
		const wrapper = mountForm();
		await VueTestUtils.flushPromises();

		expect( wrapper.vm.availablePaymentMethods ).toEqual( [ 'card', 'paypal', 'applepay' ] );
		expect( wrapper.emitted( 'donationError' ) ).toBeUndefined();
	} );

	it( 'never offers a wallet the browser can\'t use when it has form fields either', async () => {
		isApplePayAvailable.mockResolvedValue( false );
		const wrapper = mountForm( { appleFormFields: AU_APPLE_FIELDS } );
		await VueTestUtils.flushPromises();

		expect( wrapper.vm.availablePaymentMethods ).not.toContain( 'applepay' );
		expect( wrapper.emitted( 'donationError' ) ).toBeUndefined();
	} );

	it( 'clears the selection, rather than selecting card, when a preselected wallet turns out unusable', async () => {
		isApplePayAvailable.mockResolvedValue( false );
		const wrapper = mountForm( { appleFormFields: AU_APPLE_FIELDS, paymentMethod: 'applepay' } );
		await VueTestUtils.flushPromises();

		expect( wrapper.findComponent( ApplePayComponent ).exists() ).toBe( false );
		expect( wrapper.emitted( 'onPaymentMethodChange' ) ).toEqual( [ [ '' ] ] );
		// Card may need an amount the donor hasn't chosen yet to create its checkout session
		expect( wrapper.findComponent( GravyCardForm ).exists() ).toBe( false );
	} );

	it( 'opens a preselected wallet once the browser can use it', async () => {
		const wrapper = mountForm( { appleFormFields: AU_APPLE_FIELDS, paymentMethod: 'applepay' } );
		await VueTestUtils.flushPromises();
		expect( wrapper.findComponent( ApplePayComponent ).exists() ).toBe( true );
	} );

	// The checks above passed, so this should not happen; if it does, the donor is told
	// rather than having the selected method disappear without any messaging
	it( 'shows the donor the error of a selected wallet that turns out unusable once open', async () => {
		const wrapper = mountForm( { paymentMethod: 'googlepay' } );
		await VueTestUtils.flushPromises();

		const error = { type: 'validation', messages: [ 'Google Pay is not available.' ] };
		wrapper.findComponent( GooglePayComponent ).vm.$emit( 'error', error );
		await VueTestUtils.flushPromises();

		expect( wrapper.emitted( 'donationError' ) ).toEqual( [ [ error ] ] );
		expect( wrapper.emitted( 'onPaymentMethodChange' ) ).toBeUndefined();
	} );

	it( 'does the same for a selected wallet with form fields', async () => {
		const wrapper = mountForm( { appleFormFields: AU_APPLE_FIELDS, paymentMethod: 'applepay' } );
		await VueTestUtils.flushPromises();

		const error = { type: 'validation', messages: [ 'Apple Pay is not available.' ] };
		wrapper.findComponent( ApplePayComponent ).vm.$emit( 'error', error );
		await VueTestUtils.flushPromises();

		expect( wrapper.emitted( 'donationError' ) ).toEqual( [ [ error ] ] );
		expect( wrapper.emitted( 'onPaymentMethodChange' ) ).toBeUndefined();
	} );
} );
