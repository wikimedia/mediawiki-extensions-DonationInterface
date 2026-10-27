/* global describe it expect jest beforeEach afterEach */

jest.mock( '../../../modules/ext.donationInterface.comboWiki/api.js', () => Object.assign(
	{},
	jest.requireActual( '../../../modules/ext.donationInterface.comboWiki/api.js' ),
	{ submitDonation: jest.fn( () => new Promise( () => {} ) ) }
) );
// The browser can use Google Pay, which PaymentMethodForm checks before showing it
jest.mock( '../../../modules/ext.donationInterface.comboWiki/walletAvailability.js', () => ( {
	isApplePayAvailable: jest.fn( () => Promise.resolve( true ) ),
	isGooglePayAvailable: jest.fn( () => Promise.resolve( true ) )
} ) );

const VueTestUtils = require( '@vue/test-utils' );
const api = require( '../../../modules/ext.donationInterface.comboWiki/api.js' );
const PaymentMethodForm = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodForm.vue' );
const PaymentMethodFormFields = require( '../../../modules/ext.donationInterface.comboWiki/components/PaymentMethodFormFields.vue' );
const GooglePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/GooglePayComponent.vue' );
const ApplePayComponent = require( '../../../modules/ext.donationInterface.comboWiki/components/ApplePayComponent.vue' );
const { useAppState } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useAppState.js' );

const appState = useAppState();

// AU asks for the state, which the PayPal and Google Pay validation blocks don't switch off
const STATE_FIELDS = { state_province: true };
const STATE_RULES = { state_province: [ { required: true, message: 'Please select your state' } ] };
const AU_PARAMS = {
	gateway: 'gravy',
	paymentMethods: [
		{ method: 'paypal', gateway: 'gravy' },
		{ method: 'google', gateway: 'gravy' }
	],
	DonationInterfaceFormFields: { method: { paypal: STATE_FIELDS, google: STATE_FIELDS } },
	DonateValidationRules: { method: { paypal: STATE_RULES, google: STATE_RULES } }
};

async function mountForm( { paymentMethod = 'paypal', frequency = 'monthly', params = AU_PARAMS } = {} ) {
	const wrapper = VueTestUtils.mount( PaymentMethodForm, {
		props: {
			donation: { frequency, amount: 10, currency: 'AUD', country: 'AU', paymentMethod }
		},
		global: {
			provide: { params },
			// Google's SDK isn't loaded in jest; these tests only need what the wallet submits
			stubs: { 'googlepay-form': true }
		}
	} );
	// Wallets show once their availability check resolves
	await VueTestUtils.flushPromises();
	return wrapper;
}

// cdx-field gives its input a generated id, so find it by placeholder (the i18n key here)
const stateInput = ( wrapper ) => wrapper.find( 'input[placeholder^="donate_interface-donor-state_province"]' );
const button = ( wrapper, text ) => wrapper.findAll( 'button' ).find( ( b ) => b.text() === text );

describe( 'ComboWiki details form for the selected payment method', () => {
	beforeEach( () => {
		api.submitDonation.mockClear();
		appState.setShowRecurringConvert( false );
	} );

	it( 'shows the method\'s fields once, above its own part', async () => {
		const wrapper = await mountForm();
		expect( wrapper.findAllComponents( PaymentMethodFormFields ) ).toHaveLength( 1 );
		expect( stateInput( wrapper ).exists() ).toBe( true );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
	} );

	it( 'labels the PayPal submit button so it is not mistaken for the PayPal option', async () => {
		const wrapper = await mountForm();
		expect( button( wrapper, 'combowiki-method-paypal' ).exists() ).toBe( true );
		expect( button( wrapper, 'combowiki-donate-with-paypal' ).exists() ).toBe( true );
	} );

	it( 'does not submit while a required field is empty, and shows its error', async () => {
		const wrapper = await mountForm();
		await button( wrapper, 'combowiki-donate-with-paypal' ).trigger( 'click' );

		expect( api.submitDonation ).not.toHaveBeenCalled();
		expect( wrapper.text() ).toContain( 'Please select your state' );
	} );

	it( 'submits a recurring donation with the details', async () => {
		const wrapper = await mountForm();
		await stateInput( wrapper ).setValue( 'NSW' );
		await button( wrapper, 'combowiki-donate-with-paypal' ).trigger( 'click' );

		expect( api.submitDonation.mock.lastCall[ 1 ] ).toEqual( { state_province: 'NSW' } );
	} );

	it( 'opens the monthly convert modal for a one-time donation instead of submitting', async () => {
		const wrapper = await mountForm( { frequency: 'once' } );
		await stateInput( wrapper ).setValue( 'NSW' );
		await button( wrapper, 'combowiki-donate-with-paypal' ).trigger( 'click' );

		expect( api.submitDonation ).not.toHaveBeenCalled();
		expect( appState.showRecurringConvert.value ).toBe( true );
	} );

	it( 'sends the typed state when the wallet returns none', async () => {
		const wrapper = await mountForm( { paymentMethod: 'googlepay' } );
		await stateInput( wrapper ).setValue( 'NSW' );
		wrapper.findComponent( GooglePayComponent ).vm.$emit( 'submit', { state_province: undefined, payment_token: 'tok' } );

		expect( api.submitDonation.mock.lastCall[ 1 ] ).toEqual( { state_province: 'NSW', payment_token: 'tok' } );
	} );

	it( 'keeps the state the donor entered over the wallet\'s free text', async () => {
		const wrapper = await mountForm( { paymentMethod: 'googlepay' } );
		await stateInput( wrapper ).setValue( 'NSW' );
		wrapper.findComponent( GooglePayComponent ).vm.$emit( 'submit', { state_province: 'New South Wales', payment_token: 'tok' } );

		expect( api.submitDonation.mock.lastCall[ 1 ].state_province ).toBe( 'NSW' );
	} );

	describe( 'with a state list for the country', () => {
		const LIST_PARAMS = Object.assign( {}, AU_PARAMS, {
			DonationInterfaceStateProvinceOptions: [
				{ value: 'NSW', label: 'New South Wales' },
				{ value: 'VIC', label: 'Victoria' }
			]
		} );

		it( 'offers the states in a native select the browser can autofill', async () => {
			const wrapper = await mountForm( { params: LIST_PARAMS } );
			const select = wrapper.find( 'select#state_province' );

			expect( select.attributes( 'autocomplete' ) ).toBe( 'billing address-level1' );
			expect( select.element.value ).toBe( '' );
			expect( select.findAll( 'option' ).map( ( o ) => o.element.value ) ).toEqual( [ '', 'NSW', 'VIC' ] );
		} );

		it( 'marks the select as invalid while no state is picked', async () => {
			const wrapper = await mountForm( { params: LIST_PARAMS } );
			await button( wrapper, 'combowiki-donate-with-paypal' ).trigger( 'click' );

			expect( wrapper.find( 'select#state_province' ).classes() ).toContain( 'combo-wiki__select--error' );
		} );

		it( 'submits the picked state', async () => {
			const wrapper = await mountForm( { params: LIST_PARAMS } );
			await wrapper.find( 'select#state_province' ).setValue( 'VIC' );
			await button( wrapper, 'combowiki-donate-with-paypal' ).trigger( 'click' );

			expect( api.submitDonation.mock.lastCall[ 1 ] ).toEqual( { state_province: 'VIC' } );
		} );
	} );

	it( 'fills a field the donor left empty from the wallet', async () => {
		const wrapper = await mountForm( { paymentMethod: 'googlepay', params: Object.assign( {}, AU_PARAMS, {
			DonationInterfaceFormFields: { method: { google: { state_province: true, city: 'optional' } } }
		} ) } );
		await stateInput( wrapper ).setValue( 'NSW' );
		wrapper.findComponent( GooglePayComponent ).vm.$emit( 'submit', { city: 'Sydney', payment_token: 'tok' } );

		expect( api.submitDonation.mock.lastCall[ 1 ] ).toEqual( { state_province: 'NSW', city: 'Sydney', payment_token: 'tok' } );
	} );
} );

describe( 'ComboWiki wallets below the details form', () => {
	const donation = { frequency: 'monthly', amount: 10, currency: 'AUD', country: 'AU' };
	const donorDetails = ( valid ) => ( { valid, validate: jest.fn( () => valid ) } );
	// Neither wallet's SDK loads in jsdom, so these call the click handlers directly
	const mountWallet = ( component, details ) => VueTestUtils.mount( component, {
		props: { donation, donorDetails: details },
		global: { provide: { params: { gravyConfiguration: { googleScript: 'https://pay.google.com/gp/p/js/pay.js' } } } }
	} );

	describe( 'Google Pay', () => {
		let loadPaymentData;
		beforeEach( () => {
			loadPaymentData = jest.fn( () => new Promise( () => {} ) );
			jest.spyOn( GooglePayComponent.methods, 'getClient' ).mockReturnValue( { loadPaymentData } );
		} );
		afterEach( () => {
			jest.restoreAllMocks();
		} );

		it( 'does not open the payment sheet while the details are invalid', () => {
			const details = donorDetails( false );
			mountWallet( GooglePayComponent, details ).vm.onButtonClicked();

			expect( details.validate ).toHaveBeenCalled();
			expect( loadPaymentData ).not.toHaveBeenCalled();
		} );

		it( 'opens the payment sheet once the details are valid', () => {
			mountWallet( GooglePayComponent, donorDetails( true ) ).vm.onButtonClicked();

			expect( loadPaymentData ).toHaveBeenCalled();
		} );
	} );

	describe( 'Apple Pay', () => {
		const click = () => ( { preventDefault: () => {}, currentTarget: { disabled: false } } );
		let begin;
		beforeEach( () => {
			// Never resolves, so the button isn't set up: the tests click it directly
			mw.loader = { using: () => new Promise( () => {} ) };
			begin = jest.fn();
			window.ApplePaySession = jest.fn( () => ( { begin } ) );
		} );
		afterEach( () => {
			delete mw.loader;
			delete window.ApplePaySession;
		} );

		it( 'does not open the payment sheet while the details are invalid', () => {
			const details = donorDetails( false );
			const event = click();
			mountWallet( ApplePayComponent, details ).vm.handleApplePaySubmitClick( event );

			expect( details.validate ).toHaveBeenCalled();
			expect( begin ).not.toHaveBeenCalled();
			// So the donor can try again once the form is fixed
			expect( event.currentTarget.disabled ).toBe( false );
		} );

		it( 'opens the payment sheet once the details are valid', () => {
			mountWallet( ApplePayComponent, donorDetails( true ) ).vm.handleApplePaySubmitClick( click() );

			expect( begin ).toHaveBeenCalled();
		} );
	} );
} );
