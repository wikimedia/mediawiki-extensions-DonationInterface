/* global describe it expect jest */

const VueTestUtils = require( '@vue/test-utils' );
const { toRaw } = require( 'vue' );

jest.mock( '../../../modules/ext.donationInterface.comboWiki/api.js', () => ( {
	submitDonation: jest.fn( () => new Promise( () => {} ) )
} ) );

const api = require( '../../../modules/ext.donationInterface.comboWiki/api.js' );
const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );

// The page gets SmashPig's full list from Special:ComboWiki, which ComboWikiTest.php checks.
// These tests only need one currency without cents.
const EXAMPLE_NO_DECIMAL_CURRENCIES = [ 'JPY' ];

// Params as init.js provides them for ?country=US, with the config values the local payments wiki serves
const PARAMS = {
	amount: '0.00',
	country: 'US',
	currency: 'USD',
	frequency_unit: '',
	order_id: '20037.1',
	payment_method: 'cc',
	payment_submethod: '',
	recurring: '',
	variant: '',
	language: 'en',
	gateway: 'gravy',
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1, EUR: 0.86770508259643, JPY: 154, SEK: 9.49 },
	DonationInterfaceNoDecimalCurrencies: EXAMPLE_NO_DECIMAL_CURRENCIES
};

/**
 * Mounts the form with the donor's choices filled in.
 * Stubbed components still render their slots, so the checkbox label can be checked.
 *
 * @param {Object} donation
 * @return {Promise<Object>}
 */
const mountForm = async ( donation ) => {
	const wrapper = VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: { params: PARAMS },
			stubs: { 'sms-optin': true },
			renderStubDefaultSlot: true
		}
	} );
	await wrapper.setData( { donation } );
	return wrapper;
};

// Expected fees follow calculateFee() in the donate wiki's MediaWiki:DonationForm.js
describe( 'ComboWiki pay the fee calculation', () => {
	const suggestFor = async ( amount, currency ) => ( await mountForm( { amount, currency } ) ).vm.suggestedFee;

	it( 'suggests no fee until there is an amount', async () => {
		expect( await suggestFor( null, 'USD' ) ).toBeNull();
		expect( await suggestFor( '', 'USD' ) ).toBeNull();
		expect( await suggestFor( 0, 'USD' ) ).toBeNull();
	} );

	it( 'suggests 4% of the amount', async () => {
		expect( await suggestFor( 50, 'USD' ) ).toBe( 2 );
		expect( await suggestFor( 100, 'EUR' ) ).toBe( 4 );
	} );

	it( 'handles an amount typed into the other amount field', async () => {
		expect( await suggestFor( '37.5', 'USD' ) ).toBe( 1.5 );
	} );

	it( 'rounds the fee to two decimal places', async () => {
		expect( await suggestFor( 33.33, 'USD' ) ).toBe( 1.33 );
	} );

	it( 'rounds the fee to a whole amount in currencies without cents', async () => {
		expect( await suggestFor( 1234, 'JPY' ) ).toBe( 49 );
	} );

	it( 'never suggests less than the default minimum', async () => {
		expect( await suggestFor( 2.75, 'USD' ) ).toBe( 0.35 );
	} );

	it( 'uses the minimum for the donation currency', async () => {
		expect( await suggestFor( 50, 'SEK' ) ).toBe( 3 );
		expect( await suggestFor( 500, 'JPY' ) ).toBe( 35 );
	} );
} );

describe( 'ComboWiki pay the fee checkbox', () => {
	const findCheckbox = ( wrapper ) => wrapper.findComponent( { name: 'CdxCheckbox' } );

	it( 'is hidden until there is an amount', async () => {
		const wrapper = await mountForm( { amount: null } );
		expect( findCheckbox( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows the fee for the chosen amount', async () => {
		const wrapper = await mountForm( { amount: 50 } );
		expect( findCheckbox( wrapper ).text() ).toBe( 'combowiki-cover-fees:[$2.00]' );
	} );
} );

describe( 'ComboWiki pay the fee maximum', () => {
	it( 'offers the fee while the total stays within the maximum', async () => {
		expect( ( await mountForm( { amount: 11500 } ) ).vm.canCoverFee ).toBe( true );
	} );

	it( 'does not offer the fee when it would go over the maximum', async () => {
		expect( ( await mountForm( { amount: 11600 } ) ).vm.canCoverFee ).toBe( false );
	} );

	it( 'converts the maximum into the donation currency', async () => {
		// 12000 USD is about 10412 EUR
		expect( ( await mountForm( { amount: 9900, currency: 'EUR' } ) ).vm.canCoverFee ).toBe( true );
		expect( ( await mountForm( { amount: 10100, currency: 'EUR' } ) ).vm.canCoverFee ).toBe( false );
	} );

	it( 'does not add a fee over the maximum even if the box was already checked', async () => {
		expect( ( await mountForm( { amount: 11600, payFee: true } ) ).vm.feeAmount ).toBe( 0 );
	} );
} );

describe( 'ComboWiki charged amount', () => {
	it( 'only adds the fee when the donor opts in', async () => {
		expect( ( await mountForm( { amount: 50, payFee: false } ) ).vm.feeAmount ).toBe( 0 );
		expect( ( await mountForm( { amount: 50, payFee: true } ) ).vm.feeAmount ).toBe( 2 );
		expect( ( await mountForm( { amount: null, payFee: true } ) ).vm.feeAmount ).toBe( 0 );
	} );

	it( 'passes the donation through unchanged without the fee', async () => {
		const wrapper = await mountForm( { amount: '50', payFee: false } );
		expect( toRaw( wrapper.vm.chargedDonation ) ).toBe( toRaw( wrapper.vm.donation ) );
	} );

	it( 'charges the amount plus the fee when the donor opts in', async () => {
		const wrapper = await mountForm( { amount: '10.33', payFee: true, email: 'donor@example.com' } );
		expect( wrapper.vm.chargedDonation.amount ).toBe( 10.74 );
		expect( wrapper.vm.chargedDonation.email ).toBe( 'donor@example.com' );
		expect( wrapper.vm.donation.amount ).toBe( '10.33' );
	} );

	it( 'gives the payment methods the amount plus the fee', async () => {
		const wrapper = await mountForm( { amount: 50, payFee: true } );
		expect( wrapper.findComponent( PaymentMethodForm ).props( 'donation' ).amount ).toBe( 52 );
	} );
} );

describe( 'ComboWiki PayPal and Venmo submit after the monthly convert modal', () => {
	const submitFromModal = ( wrapper, updatedDonation ) => {
		wrapper.vm.submitPreModalDonation( updatedDonation );
		return api.submitDonation.mock.calls[ 0 ][ 0 ];
	};

	it( 'charges the fee when the donor keeps the one time donation', async () => {
		const wrapper = await mountForm( { amount: 50, payFee: true, paymentMethod: 'paypal' } );
		const submitted = submitFromModal( wrapper, Object.assign( {}, wrapper.vm.donation ) );
		expect( submitted.amount ).toBe( 52 );
		expect( submitted.frequency ).toBe( 'once' );
	} );

	it( 'charges the monthly ask as shown when the donor converts', async () => {
		const wrapper = await mountForm( { amount: 50, payFee: true, paymentMethod: 'paypal' } );
		const submitted = submitFromModal(
			wrapper,
			Object.assign( {}, wrapper.vm.donation, { frequency: 'monthly', amount: 10 } )
		);
		expect( submitted.amount ).toBe( 10 );
		expect( submitted.frequency ).toBe( 'monthly' );
	} );
} );
