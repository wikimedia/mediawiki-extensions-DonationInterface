/* global describe it expect jest afterAll */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const AmountSelector = require( '../../../modules/ext.donationInterface.comboWiki/components/AmountSelector.vue' );

// Params for a visit to Special:Donate from Italy.
const PARAMS = {
	amount: '0.00',
	country: 'IT',
	currency: 'EUR',
	order_id: '1336.1',
	payment_method: 'cc',
	gateway: 'adyen',
	paymentMethods: [ { method: 'cc', gateway: 'adyen' } ],
	language: 'it',
	monthlyConvertReturn: false,
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1, EUR: 0.86770508259643 },
	wgDonationInterfaceCountries: {
		ES: { currency: 'EUR', label: 'Spagna', value: 'ES' },
		IT: { currency: 'EUR', label: 'Italia', value: 'IT' },
		US: { currency: 'USD', label: 'Stati Uniti', value: 'US' }
	}
};

// jsdom can't navigate, so the reload itself is replaced
const redirectTargetUrl = jest.spyOn( PaymentForm.methods, 'redirectTargetUrl' ).mockImplementation( () => {} );

afterAll( () => {
	redirectTargetUrl.mockRestore();
} );

/**
 * Loads the form at a URL, fills in the donor's choices, then picks a new country.
 *
 * @param {string} queryString The URL the donor landed on
 * @param {Object} donation The donor's choices before changing country
 * @param {string} newCountry
 * @return {Promise<URLSearchParams>} The query of the URL the page reloads at
 */
const changeCountry = async ( queryString, donation, newCountry ) => {
	window.history.replaceState( {}, '', '/index.php?' + queryString );
	const wrapper = VueTestUtils.shallowMount( PaymentForm, {
		global: {
			provide: { params: PARAMS },
			stubs: { 'sms-optin': true }
		}
	} );
	// donation comes from setup(), which setData doesn't reach
	Object.assign( wrapper.vm.donation, donation );
	await wrapper.vm.$nextTick();
	wrapper.findComponent( AmountSelector ).vm.$emit( 'update:country', newCountry );
	expect( redirectTargetUrl ).toHaveBeenCalledTimes( 1 );
	return new URL( redirectTargetUrl.mock.calls[ 0 ][ 0 ] ).searchParams;
};

describe( 'ComboWiki country change', () => {
	it( 'keeps the other params in the URL', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT&uselang=it&utm_medium=sitenotice', {}, 'ES' );
		expect( query.get( 'title' ) ).toBe( 'Special:Donate' );
		expect( query.get( 'uselang' ) ).toBe( 'it' );
		expect( query.get( 'utm_medium' ) ).toBe( 'sitenotice' );
		expect( query.get( 'country' ) ).toBe( 'ES' );
		expect( query.get( 'currency' ) ).toBe( 'EUR' );
	} );

	it( 'keeps a yearly frequency', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT', { frequency: 'year' }, 'ES' );
		expect( query.get( 'recurring' ) ).toBe( '1' );
		expect( query.get( 'frequency_unit' ) ).toBe( 'year' );
	} );

	it( 'keeps a monthly frequency', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT', { frequency: 'month' }, 'US' );
		expect( query.get( 'recurring' ) ).toBe( '1' );
		expect( query.get( 'frequency_unit' ) ).toBe( 'month' );
	} );

	it( 'says one time explicitly when the donor switched away from a monthly banner', async () => {
		const query = await changeCountry(
			'title=Special:Donate&country=IT&recurring=1&frequency_unit=month',
			{ frequency: 'once' },
			'ES'
		);
		expect( query.get( 'recurring' ) ).toBe( '0' );
		expect( query.has( 'frequency_unit' ) ).toBe( false );
	} );

	it( 'keeps the amount when the currency stays the same', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT', { amount: 10 }, 'ES' );
		expect( query.get( 'amount' ) ).toBe( '10' );
	} );

	it( 'keeps an amount typed into the other amount field', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT', { amount: '37.5' }, 'ES' );
		expect( query.get( 'amount' ) ).toBe( '37.5' );
	} );

	it( 'drops the amount when the currency changes', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT&amount=10', { amount: 10 }, 'US' );
		expect( query.get( 'currency' ) ).toBe( 'USD' );
		expect( query.has( 'amount' ) ).toBe( false );
	} );

	it( 'drops a banner amount the donor cleared', async () => {
		const query = await changeCountry( 'title=Special:Donate&country=IT&amount=10', { amount: '' }, 'ES' );
		expect( query.has( 'amount' ) ).toBe( false );
	} );
} );
