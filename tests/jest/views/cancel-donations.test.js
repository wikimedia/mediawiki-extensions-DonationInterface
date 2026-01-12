/* global global describe it expect beforeEach afterEach jest */
/* eslint-disable es-x/no-promise */

// Mock vue router composables
jest.mock( 'vue-router', () => ( Object.assign( jest.requireActual( 'vue-router' ), { useRoute: jest.fn() } ) ) );

const VueTestUtils = require( '@vue/test-utils' );
const { when } = require( 'jest-when' );
const { useRoute } = require( 'vue-router' );

const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const CancelDonationsView = require( '../../../modules/ext.donationInterface.donorPortal/views/CancelDonations.vue' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );

const RECURRING_PAUSE_API_ACTION = 'requestPauseRecurring';
const RECURRING_CANCEL_API_ACTION = 'requestCancelRecurring';
describe( 'Cancel donations view', () => {
	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
		when( global.mw.config.get ).calledWith( 'requestDonorPortalPage' ).mockReturnValue( 'DonorPortal' );
		when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'help@example.com' );
		when( global.mw.config.get ).calledWith( 'emailPreferencesUrl' ).mockReturnValue( 'https://emailprefs.wiki' );
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

	it( 'Pause Donations view renders successfully', () => {
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );
		expect( cancelDonationsViewBody.exists() ).toBe( true );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-cancel-recurring-other-ways-heading' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-cancel-recurring-other-ways-text' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-cancel-recurring-pause-alternative-header' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-cancel-recurring-pause-alternative-text' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-pause-recurring-pause-button' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-header' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-text' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-amount-change-alternative-header' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-amount-change-alternative-text' );
		expect( cancelDonationsViewBody.html() ).toContain(
			'donorportal-cancel-recurring-amount-change-alternative-button' );

		// Ensure duration option list are rendered and visible
		const pauseDonationsOptionsList = wrapper.findAll( '#radio-button-options-list' );
		expect( pauseDonationsOptionsList.length ).toBe( 3 );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-pause-recurring-days:[30]' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-pause-recurring-days:[60]' );
		expect( cancelDonationsViewBody.html() ).toContain( 'donorportal-pause-recurring-days:[90]' );

		// Ensure pause recurring success text is not visible on first load
		const pauseSuccessText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( pauseSuccessText.exists() ).toBe( false );

		// Ensure pause recurring failure text is not visible on first load
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );

		// Ensure cancel recurring success text is not visible on first load
		const cancelSuccessText = wrapper.find( '#recurring-contribution-cancel-success' );
		expect( cancelSuccessText.exists() ).toBe( false );
	} );

	it( 'Renders the pause recurring success view on success', async () => {
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );

		when( global.mw.Api.prototype.post ).calledWith( {
			duration: '60 Days',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: Number( DonorDataMock.recurringContributions[ 0 ].id ),
			next_sched_contribution_date: DonorDataMock.recurringContributions[ 0 ].next_sched_contribution_date,
			action: RECURRING_PAUSE_API_ACTION,
			is_from_save_flow: true
		} ).mockResolvedValueOnce( {
				result: {
					message: 'Success',
					next_sched_contribution_date: '2025-10-02 00:00:02'
				}
			}
		);

		const selectedPeriod = cancelDonationsViewBody.find( '#option-60days' );
		selectedPeriod.element.selected = true;
		await selectedPeriod.trigger( 'input' );
		await VueTestUtils.flushPromises();

		const pauseRecurringOption = cancelDonationsViewBody.find( '#pause-recurring-alt' );
		const pauseRecurringOptionButton = pauseRecurringOption.find( '#submit-pause-action' );
		await pauseRecurringOptionButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( successText.exists() ).toBe( true );
		expect( successText.html() ).toContain( '2025-10-02 00:00:02' );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );

		// Ensure cancel recurring success text is not visible on first load
		const cancelSuccessText = wrapper.find( '#recurring-contribution-cancel-success' );
		expect( cancelSuccessText.exists() ).toBe( false );
	} );

	it( 'Renders the pause error view on failure', async () => {
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		when( global.mw.Api.prototype.post ).calledWith( {
			action: RECURRING_PAUSE_API_ACTION,
			duration: '90 Days',
			contact_id: DonorDataMock.contact_id,
			checksum: DonorDataMock.checksum,
			contribution_recur_id: '123',
			next_sched_contribution_date: '2025-08-02 00:00:02',
			is_from_save_flow: true
		} ).mockRejectedValueOnce( {
				result: {
					message: 'API error'
				}
			}
		);

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );

		const selectedPeriod = cancelDonationsViewBody.find( '#option-90days' );
		selectedPeriod.element.selected = true;
		await selectedPeriod.trigger( 'input' );
		await VueTestUtils.flushPromises();

		const pauseRecurringOption = cancelDonationsViewBody.find( '#pause-recurring-alt' );
		const pauseRecurringOptionButton = pauseRecurringOption.find( '#submit-pause-action' );
		await pauseRecurringOptionButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( true );
		expect( failureText.html() ).toContain( 'donorportal-pause-failure' );

		// Ensure cancel recurring success text is not visible on first load
		const cancelSuccessText = wrapper.find( '#recurring-contribution-cancel-success' );
		expect( cancelSuccessText.exists() ).toBe( false );
	} );

	it( 'Renders the cancel confirmation page and the recurring success view on successful cancel', async () => {
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );

		when( global.mw.Api.prototype.post ).calledWith( {
			reason: 'Update',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: Number( DonorDataMock.recurringContributions[ 0 ].id ),
			action: RECURRING_CANCEL_API_ACTION
		} ).mockResolvedValueOnce( {
				result: {
					message: 'Success'
				}
			}
		);

		const proceedCancelButton = cancelDonationsViewBody.find( '#continue' );
		await proceedCancelButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const cancelConfirmationScreen = wrapper.find( '#recurring-cancellation-confirmation' );
		expect( cancelConfirmationScreen.exists() ).toBe( true );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-header' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-text' );
		expect( cancelConfirmationScreen.html() ).toContain( DonorDataMock.recurringContributions[ 0 ].amount_frequency_key );
		expect( cancelConfirmationScreen.html() ).toContain( DonorDataMock.recurringContributions[ 0 ].payment_method );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-request-for-reason' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-cancel-button' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-changed-my-mind' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-switch-to-annual' );

		const givingMethodReason = cancelConfirmationScreen.find( '#option-giving-method' );
		await givingMethodReason.trigger( 'input' );

		const submitButton = cancelConfirmationScreen.find( '#continue' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure failure text is not visible after successful API request
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( false );

		// Ensure cancel recurring success text is not visible on first load
		const cancelSuccessText = wrapper.find( '#recurring-contribution-cancel-success' );
		expect( cancelSuccessText.exists() ).toBe( true );
		expect( cancelSuccessText.html() ).toContain( `donorportal-cancel-monthly-recurring-confirmation-text:[<strong>${ DonorDataMock.recurringContributions[ 0 ].amount_frequency_key }:[${ DonorDataMock.recurringContributions[ 0 ].amount_formatted },${ DonorDataMock.recurringContributions[ 0 ].currency }]</strong>]` );
	} );

	it( 'Renders the cancel confirmation page and the recurring error view on failed cancel', async () => {
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );

		when( global.mw.Api.prototype.post ).calledWith( {
			reason: 'Update',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: Number( DonorDataMock.recurringContributions[ 0 ].id ),
			action: RECURRING_CANCEL_API_ACTION
		} ).mockRejectedValueOnce( {
				result: {
					message: 'API error'
				}
			}
		);

		const proceedCancelButton = cancelDonationsViewBody.find( '#continue' );
		await proceedCancelButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const cancelConfirmationScreen = wrapper.find( '#recurring-cancellation-confirmation' );
		expect( cancelConfirmationScreen.exists() ).toBe( true );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-header' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-text' );
		expect( cancelConfirmationScreen.html() ).toContain( DonorDataMock.recurringContributions[ 0 ].amount_frequency_key );
		expect( cancelConfirmationScreen.html() ).toContain( DonorDataMock.recurringContributions[ 0 ].payment_method );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-request-for-reason' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-cancel-button' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-changed-my-mind' );
		expect( cancelConfirmationScreen.html() ).toContain( 'donorportal-cancel-recurring-switch-to-annual' );

		const givingMethodReason = cancelConfirmationScreen.find( '#option-giving-method' );
		await givingMethodReason.trigger( 'input' );

		const submitButton = cancelConfirmationScreen.find( '#continue' );
		await submitButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( successText.exists() ).toBe( false );

		// Ensure cancel recurring success text is not visible on first load
		const cancelSuccessText = wrapper.find( '#recurring-contribution-cancel-success' );
		expect( cancelSuccessText.exists() ).toBe( false );

		// Ensure recurring failure text is visible on first load
		const failureText = wrapper.find( '#error-component' );
		expect( failureText.exists() ).toBe( true );
		expect( failureText.html() ).toContain( 'donorportal-cancel-failure' );

	} );

	it( 'Submits a tracking value from the URL', async () => {
		window.history.pushState( {}, '', '/donorPortal?wmf_campaign=testCampaign' );
		const wrapper = VueTestUtils.mount( CancelDonationsView, {
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationsViewBody = wrapper.find( '#cancel-donations-form' );

		when( global.mw.Api.prototype.post ).calledWith( {
			duration: '60 Days',
			contact_id: Number( DonorDataMock.contact_id ),
			checksum: DonorDataMock.checksum,
			contribution_recur_id: Number( DonorDataMock.recurringContributions[ 0 ].id ),
			next_sched_contribution_date: DonorDataMock.recurringContributions[ 0 ].next_sched_contribution_date,
			action: RECURRING_PAUSE_API_ACTION,
			wmf_campaign: 'testCampaign',
			is_from_save_flow: true
		} ).mockResolvedValueOnce( {
				result: {
					message: 'Success',
					next_sched_contribution_date: '2025-10-02 00:00:02'
				}
			}
		);

		const selectedPeriod = cancelDonationsViewBody.find( '#option-60days' );
		selectedPeriod.element.selected = true;
		await selectedPeriod.trigger( 'input' );
		await VueTestUtils.flushPromises();

		const pauseRecurringOption = cancelDonationsViewBody.find( '#pause-recurring-alt' );
		const pauseRecurringOptionButton = pauseRecurringOption.find( '#submit-pause-action' );
		await pauseRecurringOptionButton.trigger( 'click' );
		await VueTestUtils.flushPromises();

		// Ensure pause success text is visible after successful API request
		const successText = wrapper.find( '#recurring-contribution-pause-success' );
		expect( successText.exists() ).toBe( true );
		window.history.back();
	} );
} );
