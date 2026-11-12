/* global describe it expect jest */

const VueTestUtils = require( '@vue/test-utils' );
const { toRaw } = require( 'vue' );

jest.mock( '../../../modules/ext.donationInterface.comboWiki/api.js', () => ( {
	submitDonation: jest.fn( () => new Promise( () => {} ) )
} ) );

const api = require( '../../../modules/ext.donationInterface.comboWiki/api.js' );
const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const AmountSelector = require( '../../../modules/ext.donationInterface.comboWiki/components/AmountSelector.vue' );

// The page gets SmashPig's full list from Special:Donate, which ComboWikiTest.php checks.
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
 * Mounts the form with the donor's choices filled in. The amount selector is rendered, and
 * the Codex components in it are stubbed with their slots, so their labels can be checked.
 *
 * @param {Object} donation
 * @param {Object} [params] Replaces some of PARAMS
 * @return {Promise<Object>}
 */
const mountForm = async ( donation, params = {} ) => {
	const wrapper = VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: { params: Object.assign( {}, PARAMS, params ) },
			stubs: { 'sms-optin': true, 'amount-selector': AmountSelector },
			renderStubDefaultSlot: true
		}
	} );
	// donation comes from setup(), which setData doesn't reach
	Object.assign( wrapper.vm.donation, donation );
	await wrapper.vm.$nextTick();
	return wrapper;
};

const amountSelector = ( wrapper ) => wrapper.findComponent( AmountSelector );
const presetButtons = ( wrapper ) => wrapper.findAllComponents( { name: 'CdxToggleButton' } );
const otherAmountInput = ( wrapper ) => wrapper.findComponent( { name: 'CdxTextInput' } );
const findCheckbox = ( wrapper ) => wrapper.findComponent( { name: 'CdxCheckbox' } );

describe( 'ComboWiki amount', () => {
	it( 'offers the preset amounts in the donation currency', async () => {
		const wrapper = await mountForm( { amount: null } );
		expect( presetButtons( wrapper ).map( ( button ) => button.text() ) )
			.toEqual( [ '$2.75', '$5.00', '$10.00', '$20.00', '$30.00', '$50.00', '$100.00' ] );
	} );

	it( 'converts the presets into other currencies, rounded up', async () => {
		const wrapper = await mountForm( { amount: null, currency: 'SEK' } );
		// 2.75 USD is 26.0975 SEK, and 100 USD is 949 SEK, rounded up to the next 5
		expect( presetButtons( wrapper )[ 0 ].text() ).toContain( '27' );
		expect( presetButtons( wrapper )[ 6 ].text() ).toContain( '950' );
	} );

	it( 'sets the donation amount to the preset the donor picks', async () => {
		const wrapper = await mountForm( { amount: null } );
		await presetButtons( wrapper )[ 2 ].vm.$emit( 'update:modelValue', true );
		expect( wrapper.vm.donation.amount ).toBe( 10 );
	} );

	it( 'shows the chosen preset as selected', async () => {
		const wrapper = await mountForm( { amount: 20 } );
		expect( presetButtons( wrapper ).map( ( button ) => button.props( 'modelValue' ) ) )
			.toEqual( [ false, false, false, true, false, false, false ] );
	} );

	it( 'sets the donation amount to what the donor types in the other amount field', async () => {
		const wrapper = await mountForm( { amount: null } );
		await otherAmountInput( wrapper ).vm.$emit( 'update:modelValue', '37.5' );
		expect( wrapper.vm.donation.amount ).toBe( '37.5' );
	} );

	it( 'shows no preset as selected while there is an other amount', async () => {
		const wrapper = await mountForm( { amount: null } );
		await otherAmountInput( wrapper ).vm.$emit( 'update:modelValue', '20' );
		expect( presetButtons( wrapper ).every( ( button ) => button.props( 'modelValue' ) === false ) ).toBe( true );
	} );

	it( 'starts on the preset for an amount from the URL', async () => {
		const wrapper = await mountForm( {}, { amount: '50' } );
		expect( wrapper.vm.donation.amount ).toBe( 50 );
		expect( otherAmountInput( wrapper ).props( 'modelValue' ) ).toBe( '' );
	} );

	it( 'puts an amount from the URL that isn\'t a preset in the other amount field', async () => {
		const wrapper = await mountForm( {}, { amount: '42' } );
		expect( wrapper.vm.donation.amount ).toBe( '42' );
		expect( otherAmountInput( wrapper ).props( 'modelValue' ) ).toBe( '42' );
	} );
} );

// Expected fees follow calculateFee() in the donate wiki's MediaWiki:DonationForm.js
describe( 'ComboWiki pay the fee calculation', () => {
	const suggestFor = async ( amount, currency ) => amountSelector( await mountForm( { amount, currency } ) ).vm.suggestedFee;

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
	it( 'is hidden until there is an amount', async () => {
		const wrapper = await mountForm( { amount: null } );
		expect( findCheckbox( wrapper ).exists() ).toBe( false );
	} );

	it( 'shows the fee for the chosen amount', async () => {
		const wrapper = await mountForm( { amount: 50 } );
		expect( findCheckbox( wrapper ).text() ).toBe( 'combowiki-cover-fees:[$2.00]' );
	} );

	it( 'shows whether the donor opted in', async () => {
		const wrapper = await mountForm( { amount: 50, payFee: true } );
		expect( findCheckbox( wrapper ).props( 'modelValue' ) ).toBe( true );
	} );

	it( 'adds the fee to the donation when the donor ticks it', async () => {
		const wrapper = await mountForm( { amount: 50, payFee: false } );
		await findCheckbox( wrapper ).vm.$emit( 'update:modelValue', true );
		expect( wrapper.vm.donation.payFee ).toBe( true );
		expect( wrapper.vm.chargedDonation.amount ).toBe( 52 );
	} );
} );

describe( 'ComboWiki pay the fee maximum', () => {
	const canCoverFee = async ( donation ) => amountSelector( await mountForm( donation ) ).vm.canCoverFee;

	it( 'offers the fee while the total stays within the maximum', async () => {
		expect( await canCoverFee( { amount: 11500 } ) ).toBe( true );
	} );

	it( 'does not offer the fee when it would go over the maximum', async () => {
		expect( await canCoverFee( { amount: 11600 } ) ).toBe( false );
		expect( findCheckbox( await mountForm( { amount: 11600 } ) ).exists() ).toBe( false );
	} );

	it( 'converts the maximum into the donation currency', async () => {
		// 12000 USD is about 10412 EUR
		expect( await canCoverFee( { amount: 9900, currency: 'EUR' } ) ).toBe( true );
		expect( await canCoverFee( { amount: 10100, currency: 'EUR' } ) ).toBe( false );
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

	it( 'charges a new amount and fee choice straight away', async () => {
		const wrapper = await mountForm( { amount: null, payFee: false } );

		amountSelector( wrapper ).vm.$emit( 'update:modelValue', 50 );
		amountSelector( wrapper ).vm.$emit( 'update:payFee', true );

		// No tick: what's charged must already match, e.g. for submitPreModalDonation
		expect( wrapper.vm.donation.amount ).toBe( 50 );
		expect( wrapper.vm.chargedDonation.amount ).toBe( 52 );
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

describe( 'ComboWiki payment form validation before a payment method', () => {
	const SHOWS_OPT_IN = { DonationInterfaceFormFields: { shared: { opt_in: true } } };

	// What PaymentMethodForm calls before it opens or submits a payment method
	const canSelectPaymentMethod = ( wrapper ) => wrapper.findComponent( PaymentMethodForm ).props( 'beforeSelect' )();
	const optInFieldset = ( wrapper ) => wrapper.findComponent( { name: 'OptinFieldset' } );

	it( 'blocks the payment method until the donor picks an amount', async () => {
		// Special:Donate sends 0.00 when the URL has no amount
		const wrapper = await mountForm( {} );

		expect( canSelectPaymentMethod( wrapper ) ).toBe( false );
		await wrapper.vm.$nextTick();
		expect( amountSelector( wrapper ).props( 'status' ) ).toBe( 'error' );
		expect( amountSelector( wrapper ).props( 'messages' ) ).toEqual( { error: 'combowiki-error-select-amount' } );
	} );

	it( 'names the minimum in the donation currency in the amount error', async () => {
		await mountForm( {}, { currency: 'SEK' } );
		// 1 USD is 9.49 SEK, rounded up
		expect( mw.msg ).toHaveBeenCalledWith( 'combowiki-error-select-amount', expect.stringContaining( '10' ) );
	} );

	it( 'blocks the payment method for an amount under the minimum', async () => {
		const wrapper = await mountForm( { amount: '0.50' } );
		expect( canSelectPaymentMethod( wrapper ) ).toBe( false );
		expect( wrapper.vm.status.amount ).toBe( 'error' );
	} );

	it( 'lets the donor pick a payment method once there is an amount', async () => {
		const wrapper = await mountForm( { amount: 10 } );
		expect( canSelectPaymentMethod( wrapper ) ).toBe( true );
		expect( wrapper.vm.status.amount ).toBe( 'default' );
	} );

	it( 'clears the amount error as soon as the donor picks an amount', async () => {
		const wrapper = await mountForm( {} );
		canSelectPaymentMethod( wrapper );

		await presetButtons( wrapper )[ 2 ].vm.$emit( 'update:modelValue', true );

		expect( wrapper.vm.status.amount ).toBe( 'default' );
		expect( canSelectPaymentMethod( wrapper ) ).toBe( true );
	} );

	it( 'blocks the payment method until the donor answers the email opt in', async () => {
		const wrapper = await mountForm( { amount: 10, optIn: undefined }, SHOWS_OPT_IN );

		expect( canSelectPaymentMethod( wrapper ) ).toBe( false );
		await wrapper.vm.$nextTick();
		expect( optInFieldset( wrapper ).props( 'status' ) ).toBe( 'error' );
		expect( optInFieldset( wrapper ).props( 'messages' ) ).toEqual( { error: 'combowiki-error-select-email-option' } );
	} );

	it( 'clears the opt in error as soon as the donor answers', async () => {
		const wrapper = await mountForm( { amount: 10, optIn: undefined }, SHOWS_OPT_IN );
		canSelectPaymentMethod( wrapper );

		optInFieldset( wrapper ).vm.$emit( 'update:modelValue', '0' );
		optInFieldset( wrapper ).vm.$emit( 'onChange', '0' );

		expect( wrapper.vm.status.optIn ).toBe( 'default' );
		expect( canSelectPaymentMethod( wrapper ) ).toBe( true );
	} );

	it( 'does not ask for the email opt in when the form does not show it', async () => {
		const wrapper = await mountForm( { amount: 10, optIn: undefined } );
		expect( optInFieldset( wrapper ).exists() ).toBe( false );
		expect( canSelectPaymentMethod( wrapper ) ).toBe( true );
	} );

	it( 'shows both errors when the amount and opt in are missing', async () => {
		const wrapper = await mountForm( { optIn: undefined }, SHOWS_OPT_IN );
		expect( wrapper.vm.validateAll() ).toEqual( [ 'amount', 'optIn' ] );
	} );
} );
