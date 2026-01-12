/* global global describe it expect beforeEach afterEach jest */
/* eslint-disable es-x/no-promise */

// Mock vue router composables
jest.mock( 'vue-router', () => ( Object.assign( jest.requireActual( 'vue-router' ), { useRoute: jest.fn() } ) ) );

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const { useRoute } = require( 'vue-router' );

const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const DowngradeAmountView = require( '../../../modules/ext.donationInterface.donorPortal/views/AmountDowngrade.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

const RECURRING_UPDATE_API_ACTION = 'requestUpdateRecurring';
describe( 'Downgrade donations view', () => {
	window.alert = jest.fn();
	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
		when( global.mw.config.get ).calledWith( 'requestDonorPortalPage' ).mockReturnValue( 'DonorPortal' );
		when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'help@example.com' );
		when( global.mw.config.get ).calledWith( 'recurringUpgradeMaxUSD' ).mockReturnValue( 1000000 );
		when( global.mw.config.get ).calledWith( 'wgDonationInterfaceCurrencyRates' ).mockReturnValue( [
			[ 'USD', 1 ],
			[ 'EUR', 0.9 ],
			[ 'GBP', 0.8 ]
		] );
		useRoute.mockImplementationOnce( () => ( {
			params: {
				id: '123'
			}
		} ) );
	} );

	afterEach( () => {
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( resolve, _ ) => {
				resolve( {} );
			} )
		);
	} );

	it( 'Downgrade Donations view renders successfully', () => {
		const wrapper = VueTestUtils.mount( DowngradeAmountView, {
			global: {
				plugins: [ router ]
			}
		} );

		const updateDonationViewBody = wrapper.find( '#update-donations-form' );
		expect( updateDonationViewBody.exists() ).toBe( true );
		expect( updateDonationViewBody.html() ).toContain( 'donorportal-downgrade-recurring-heading' );
		expect( updateDonationViewBody.html() ).toContain( 'donorportal-update-recurring-text' );
		expect( updateDonationViewBody.html() ).toContain( 'donorportal-update-recurring-new-donation' );
		expect( updateDonationViewBody.html() ).toContain( 'donorportal-update-recurring-change-mind' );
		expect( updateDonationViewBody.html() ).toContain( 'donorportal-update-recurring-new-donation-effective-date' );

		// Ensure success text is not visible on first load
		const successText = wrapper.find( '#recurring-contribution-update-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible on first load
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );
	} );

	it( 'Renders the success view on success downgrade', async () => {
		const wrapper = VueTestUtils.mount( DowngradeAmountView, {
			global: {
				plugins: [ router ]
			}
		} );

		const updateDonationViewBody = wrapper.find( '#update-donations-form' );
		const amountInput = updateDonationViewBody.find( '#new-recurring-amount' );

		global.mw.Api.prototype.post.mockImplementation( () => Promise.resolve( {
			result: {
				message: 'Success',
				next_sched_contribution_date: '2025-10-02 00:00:02'
			} } )
		);
		amountInput.element.value = 1;
		await amountInput.trigger( 'input' );

		await VueTestUtils.flushPromises();
		const submitButton = updateDonationViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: RECURRING_UPDATE_API_ACTION,
			amount: '1',
			txn_type: 'recurring_downgrade',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: 123,
			is_from_save_flow: false
		} );

		// Ensure success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-update-success' );
		expect( successText.exists() ).toBe( true );
		expect( successText.html() ).toContain( `donorportal-update-recurring-confirmation-text:[<strong>${ contribution_mock.currency_symbol + amountInput.element.value + ' ' + contribution_mock.currency }</strong>,<strong>${ contribution_mock.next_sched_contribution_date_formatted }</strong>]` );
		expect( successText.html() ).toContain( 'donorportal-update-recurring-confirmation-header' );
		expect( successText.html() ).toContain( 'donorportal-update-recurring-confirmation-header-subtitle' );
		expect( successText.html() ).toContain( 'donorportal-return-to-account-button' );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );
	} );

	it( 'Renders the no submit if amount the same', async () => {
		const wrapper = VueTestUtils.mount( DowngradeAmountView, {
			global: {
				plugins: [ router ]
			}
		} );
		const DowngradeAmountViewBody = wrapper.find( '#update-donations-form' );
		const amountInput = DowngradeAmountViewBody.find( '#new-recurring-amount' );
		amountInput.element.value = 10;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = DowngradeAmountViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( window.alert ).toHaveBeenCalledWith( 'Please enter an amount different from your current donation.' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'Renders the no submit if amount outside of price range', async () => {
		const wrapper = VueTestUtils.mount( DowngradeAmountView, {
			props: {
				recurringContribution: contribution_mock
			},
			global: {
				plugins: [ router ]
			}
		} );
		const DowngradeAmountViewBody = wrapper.find( '#update-donations-form' );
		const amountInput = DowngradeAmountViewBody.find( '#new-recurring-amount' );
		amountInput.element.value = 0.1;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = DowngradeAmountViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( window.alert ).toHaveBeenCalledWith( `Please enter a valid amount between 1 and ${ contribution_mock.amount }.` );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'Renders the error view on failure', async () => {
		const wrapper = VueTestUtils.mount( DowngradeAmountView, {
			global: {
				plugins: [ router ]
			}
		} );
		global.mw.Api.prototype.post.mockImplementation(
			() => Promise.reject( {
				message: 'API error'
			} )
		);

		const DowngradeAmountViewBody = wrapper.find( '#update-donations-form' );
		const amountInput = DowngradeAmountViewBody.find( '#new-recurring-amount' );
		amountInput.element.value = 3;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = DowngradeAmountViewBody.find( '#submit-update-action' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: RECURRING_UPDATE_API_ACTION,
			amount: '3',
			txn_type: 'recurring_downgrade',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: 123,
			is_from_save_flow: false
		} );

		// Ensure success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-update-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( true );
		expect( failureText.html() ).toContain( 'donorportal-cancel-failure' );
	} );
} );
