/* global global describe it expect beforeEach */

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );

const RecurringConvert = require( '../../../modules/ext.donationInterface.comboWiki/components/RecurringConvert.vue' );
const { useAppState } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useAppState.js' );

const CONVERT_API_ACTION = 'di_recurring_convert';
const CLIENT_ERROR_API_ACTION = 'logPaymentsFormError';
const ORDER_ID = '20021.1';
const FAILURE_MESSAGE_KEY = 'combowiki-monthly-convert-failed';

// useAppState() hands back the same refs the component writes to.
const appState = useAppState();

// Config as Special:ComboWiki puts it on the page: the amount rules are what the
// live page returns for USD, the tiers come from $wgDonationInterfaceMonthlyConvertAmounts
// and the rates from SmashPig's CurrencyRates table.
const AMOUNT_RULES = { currency: 'USD', min: 1, max: 12000 };
const CURRENCY_RATES = { USD: 1, EUR: 0.86770508259643, GBP: 0.76116218937783 };
const CONVERT_AMOUNTS = [ [ 2.74, 0 ], [ 9, 1.75 ], [ 12, 2 ], [ 15, 2.5 ], [ 30, 5 ] ];

// A one-time card donation that has already gone through, waiting on the convert ask.
const DONATION = {
	amount: 30,
	currency: 'USD',
	country: 'US',
	gateway: 'gravy',
	paymentMethod: 'cc'
};

// What RecurringConversionApi returns when the conversion goes through: an
// 'errors' key is only added when there are errors (RecurringConversion.api.php:46).
const CONVERT_OK = {
	result: { redirect: null }
};

// What RecurringConversionApi returns when it will not convert the donation.
const CONVERT_REFUSED = {
	result: {
		redirect: null,
		errors: { general: [ 'There has been an error processing your request.' ] }
	}
};

async function mountModal() {
	const wrapper = VueTestUtils.mount( RecurringConvert, {
		props: {
			donation: DONATION,
			orderId: ORDER_ID,
			thankYouUrl: '/thank-you'
		}
	} );
	// mounted() flips isVisible, which renders the modal on the next tick.
	await VueTestUtils.flushPromises();
	return wrapper;
}

function postParams( nth ) {
	return global.mw.Api.prototype.post.mock.calls[ nth ][ 0 ];
}

describe( 'ComboWiki recurring convert', () => {
	beforeEach( () => {
		// useAppState.js holds its refs at module scope, so the error outlives
		// the component and every test in this file shares it. clearMocks only
		// resets mocks, not module state, so reset it by hand.
		appState.clearError();
		global.mw.Api.prototype.post.mockReset();
		when( global.mw.config.get ).calledWith( 'wgDonationInterfaceCurrencyRates' ).mockReturnValue( CURRENCY_RATES );
		when( global.mw.config.get ).calledWith( 'wgDonationInterfaceMonthlyConvertAmounts' ).mockReturnValue( CONVERT_AMOUNTS );
		when( global.mw.config.get ).calledWith( 'wgDonationInterfaceAmountRules' ).mockReturnValue( AMOUNT_RULES );
	} );

	it( 'records the order_id when the conversion is refused', async () => {
		global.mw.Api.prototype.post
			.mockResolvedValueOnce( CONVERT_REFUSED )
			.mockResolvedValueOnce( {} );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-yes-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 2 );
		expect( postParams( 0 ).action ).toBe( CONVERT_API_ACTION );
		expect( postParams( 1 ).action ).toBe( CLIENT_ERROR_API_ACTION );
		expect( postParams( 1 ).message ).toContain( ORDER_ID );
		expect( appState.error.value ).toContain( ORDER_ID );
		expect( appState.error.value ).toContain( FAILURE_MESSAGE_KEY );
		// The one-time donation already succeeded, so the donor is sent on either way.
		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'records the error code when the convert request fails outright', async () => {
		global.mw.Api.prototype.post
			.mockRejectedValueOnce( 'http' )
			.mockResolvedValueOnce( {} );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-yes-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( postParams( 1 ).action ).toBe( CLIENT_ERROR_API_ACTION );
		expect( postParams( 1 ).message ).toContain( 'http' );
		expect( appState.error.value ).toContain( ORDER_ID );
		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'records nothing when the donor declines', async () => {
		global.mw.Api.prototype.post.mockResolvedValueOnce( CONVERT_REFUSED );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-no-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );
		expect( postParams( 0 ).action ).toBe( CONVERT_API_ACTION );
		expect( appState.error.value ).toBeNull();
		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'still sends the donor on when the logging call itself fails', async () => {
		global.mw.Api.prototype.post
			.mockResolvedValueOnce( CONVERT_REFUSED )
			.mockRejectedValueOnce( 'badvalue' );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-yes-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.log.error ).toHaveBeenCalledWith( 'logPaymentsFormError failed', 'badvalue' );
		expect( appState.error.value ).toContain( ORDER_ID );
		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'leaves the app state clean when the conversion succeeds', async () => {
		global.mw.Api.prototype.post.mockResolvedValueOnce( CONVERT_OK );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-yes-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 1 );
		expect( appState.error.value ).toBeNull();
		expect( wrapper.emitted( 'close' ) ).toHaveLength( 1 );
	} );

	it( 'clears a message left over from an earlier failure', async () => {
		appState.setError( 'an error from earlier in the donation' );
		global.mw.Api.prototype.post.mockResolvedValueOnce( CONVERT_OK );
		const wrapper = await mountModal();

		await wrapper.find( '.mc-no-btn' ).trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( appState.error.value ).toBeNull();
	} );
} );
