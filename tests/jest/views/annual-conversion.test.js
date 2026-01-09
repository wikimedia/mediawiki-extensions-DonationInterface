/* global global describe it expect beforeEach afterEach jest */
/* eslint-disable es-x/no-promise */

// Mock vue router composables
jest.mock( 'vue-router', () => ( Object.assign( jest.requireActual( 'vue-router' ), { useRoute: jest.fn() } ) ) );

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const { useRoute } = require( 'vue-router' );

const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const AnnualConversionView = require( '../../../modules/ext.donationInterface.donorPortal/views/AnnualConversion.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

const ANNUAL_CONVERSION_API_ACTION = 'requestAnnualConversion';
describe( 'Annual conversion view', () => {
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

	it( 'Annual Conversion view renders successfully', () => {
		const wrapper = VueTestUtils.mount( AnnualConversionView, {
			global: {
				plugins: [ router ]
			}
		} );

		const annualConversionViewBody = wrapper.find( '#form-convert-yearly' );
		expect( annualConversionViewBody.exists() ).toBe( true );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-update-recurring-annual-convert-head' );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-update-recurring-annual-convert-description' );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-update-recurring-annual-convert-select-below' );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-update-recurring-annual-convert-yearly-other-amount' );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-cancel-recurring-quit-header' );
		expect( annualConversionViewBody.html() ).toContain( 'donorportal-return-to-account-button' );

		// Ensure success text is not visible on first load
		const successText = wrapper.find( '#recurring-contribution-annual-conversion-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible on first load
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );
	} );

	it( 'Renders the success view on success annual conversion', async () => {
		const wrapper = VueTestUtils.mount( AnnualConversionView, {
			props: {
				recurringContribution: contribution_mock
			},
			global: {
				plugins: [ router ]
			}
		} );

		const annualConversionViewBody = wrapper.find( '#form-convert-yearly' );
		const amountInput = annualConversionViewBody.find( '#new-annual-recurring-amount' );

		global.mw.Api.prototype.post.mockImplementation( () => Promise.resolve( {
			result: {
				message: 'Success'
			} } )
		);
		amountInput.element.value = 100;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = annualConversionViewBody.find( '#submit-annual-conversion' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: ANNUAL_CONVERSION_API_ACTION,
			amount: '100',
			next_sched_contribution_date: contribution_mock.next_contribution_date_yearly,
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: 123,
			is_from_save_flow: false
		} );

		// Ensure success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-annual-conversion-success' );
		expect( successText.exists() ).toBe( true );
		expect( successText.html() ).toContain( 'donorportal-thank-you' );
		expect( successText.html() ).toContain( 'donorportal-return-to-account-button' );
		expect( successText.html() ).toContain( 'donorportal-update-recurring-yearly-conversion-success' );
		expect( successText.html() ).toContain( `donorportal-update-recurring-yearly-conversion-on-schedule:[<strong>${ contribution_mock.next_contribution_date_yearly_formatted }</strong>]` );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );
	} );

	it( 'Renders the no submit if amount outside of price range', async () => {
		const wrapper = VueTestUtils.mount( AnnualConversionView, {
			global: {
				plugins: [ router ]
			}
		} );
		const AnnualConversionViewBody = wrapper.find( '#form-convert-yearly' );
		const amountInput = AnnualConversionViewBody.find( '#new-annual-recurring-amount' );
		amountInput.element.value = 0.1;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = AnnualConversionViewBody.find( '#submit-annual-conversion' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( window.alert ).toHaveBeenCalledWith( 'Please enter a valid amount between 1 and 1000000.' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'Renders the error view on failure', async () => {
		const wrapper = VueTestUtils.mount( AnnualConversionView, {
			global: {
				plugins: [ router ]
			}
		} );
		global.mw.Api.prototype.post.mockImplementation(
			() => Promise.reject( {
				message: 'API error'
			} )
		);

		const AnnualConversionViewBody = wrapper.find( '#form-convert-yearly' );
		const amountInput = AnnualConversionViewBody.find( '#new-annual-recurring-amount' );
		amountInput.element.value = 30;
		await amountInput.trigger( 'input' );
		await VueTestUtils.flushPromises();
		const submitButton = AnnualConversionViewBody.find( '#submit-annual-conversion' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: ANNUAL_CONVERSION_API_ACTION,
			amount: '30',
			next_sched_contribution_date: contribution_mock.next_contribution_date_yearly,
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: 123,
			is_from_save_flow: false
		} );

		// Ensure success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-annual-conversion-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( true );
		expect( failureText.html() ).toContain( 'donorportal-cancel-failure' );

	} );
} );
