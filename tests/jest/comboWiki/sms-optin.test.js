/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const SmsOptin = require( '../../../modules/ext.donationInterface.comboWiki/components/SmsOptin.vue' );

// DonationInterfaceFormFields as Special:Donate sends them on the local payments wiki.
// ?country=US&variant=smsOptin
const US_SMS_OPTIN_FIELDS = {
	street_address: true,
	phone: 'optional',
	postal_code: true,
	employer: 'optional',
	sms_opt_in: 'optional'
};
// ?country=GB&variant=smsOptin
const GB_SMS_OPTIN_FIELDS = {
	street_address: true,
	city: true,
	country: true,
	postal_code: true,
	opt_in: true
};
// ?country=IN&gateway=dlocal, the same with or without variant=smsOptin,
// since the smsOptin variant only has config for gravy
const IN_DLOCAL_FIELDS = {
	street_address: true,
	street_number: true,
	state_province: true,
	postal_code: true,
	city: true,
	phone: true,
	fiscal_number: 'optional'
};

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
	DonationInterfaceNoDecimalCurrencies: [ 'JPY' ]
};

/**
 * Mounts the form with some params replaced.
 *
 * @param {Object} params
 * @return {Object}
 */
const mountForm = ( params ) => VueTestUtils.shallowMount( PaymentForm, {
	global: {
		provide: { params: Object.assign( {}, PARAMS, params ) },
		// variantHelper.js registers SmsOptin under this name for the smsOptin variant
		components: { 'sms-optin': SmsOptin }
	}
} );

describe( 'ComboWiki SMS opt in phone label', () => {
	const phoneLabel = ( props ) => VueTestUtils.mount( SmsOptin, { props } )
		.find( 'label[for="phone"]' ).text();

	it( 'says the phone number is optional when the config makes it optional', () => {
		expect( phoneLabel( { phoneOptional: true } ) ).toBe( 'donate_interface-donor-phone-optional' );
	} );

	it( 'uses the plain phone label otherwise', () => {
		expect( phoneLabel( {} ) ).toBe( 'donate_interface-donor-phone' );
	} );
} );

describe( 'ComboWiki SMS opt in on the payment form', () => {
	it( 'shows the phone field, marked optional, where the config makes it optional', () => {
		const smsOptin = mountForm( {
			variant: 'smsOptin',
			DonationInterfaceFormFields: US_SMS_OPTIN_FIELDS
		} ).findComponent( SmsOptin );
		expect( smsOptin.exists() ).toBe( true );
		expect( smsOptin.props( 'phoneOptional' ) ).toBe( true );
	} );

	it( 'shows the phone field without the optional label where the config requires it', () => {
		const smsOptin = mountForm( {
			country: 'IN',
			currency: 'INR',
			gateway: 'dlocal',
			variant: 'smsOptin',
			DonationInterfaceFormFields: IN_DLOCAL_FIELDS
		} ).findComponent( SmsOptin );
		expect( smsOptin.exists() ).toBe( true );
		expect( smsOptin.props( 'phoneOptional' ) ).toBe( false );
	} );

	it( 'hides the SMS opt in where the config leaves the phone out', () => {
		const wrapper = mountForm( {
			country: 'GB',
			currency: 'GBP',
			variant: 'smsOptin',
			DonationInterfaceFormFields: GB_SMS_OPTIN_FIELDS
		} );
		expect( wrapper.findComponent( SmsOptin ).exists() ).toBe( false );
	} );

	it( 'hides the SMS opt in when the page has no field config', () => {
		// Special:Donate only sends the field config when it has a gateway adapter
		const wrapper = mountForm( { variant: 'smsOptin' } );
		expect( wrapper.findComponent( SmsOptin ).exists() ).toBe( false );
	} );

	it( 'leaves the SMS opt in off forms without the smsOptin variant', () => {
		const wrapper = mountForm( {
			country: 'IN',
			currency: 'INR',
			gateway: 'dlocal',
			DonationInterfaceFormFields: IN_DLOCAL_FIELDS
		} );
		expect( wrapper.findComponent( SmsOptin ).exists() ).toBe( false );
	} );
} );
