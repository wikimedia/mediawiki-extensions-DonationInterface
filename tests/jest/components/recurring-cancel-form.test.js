/* global describe it expect beforeEach afterEach jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelForm = require(
	'../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelForm.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring cancel form component', () => {
	const durationOptions = [
		{
			id: '30days',
			value: 30,
			locale: '30 days'
		},
		{
			id: '60days',
			value: 60,
			locale: '60 days'
		}
	];
	const defaultDuration = durationOptions[ 0 ];
	const submitPauseFormMock = jest.fn();
	const proceedCancelActionMock = jest.fn();

	it( 'Pause Donations form renders successfully', () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelForm, {
			props: {
				recurringContribution: contribution_mock,
				durationOptions,
				defaultDuration,
				submitPauseRecurringForm: submitPauseFormMock,
				proceedCancelAction: proceedCancelActionMock
			},
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationFormBody = wrapper.find( '#recurring-cancellation-form' );
		expect( cancelDonationFormBody.exists() ).toBe( true );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-other-ways-heading' );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-other-ways-text' );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-pause-alternative-header' );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-pause-alternative-text' );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-pause-recurring-pause-button' );
		expect( cancelDonationFormBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-header' );
		expect( cancelDonationFormBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-text' );
		expect( cancelDonationFormBody.html() ).toContain(
			'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' );
		expect( cancelDonationFormBody.html() ).toContain(
			'donorportal-cancel-recurring-amount-change-alternative-header' );
		expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-amount-change-alternative-text' );
		expect( cancelDonationFormBody.html() ).toContain(
			'donorportal-cancel-recurring-amount-change-alternative-button' );

		// Ensure duration option list are rendered and visible
		const pauseDonationsOptionsList = wrapper.findAll( '#radio-button-options-list' );
		expect( pauseDonationsOptionsList.length ).toBe( 2 );
		expect( cancelDonationFormBody.html() ).toContain( '30 days' );
		expect( cancelDonationFormBody.html() ).toContain( '60 days' );
	} );

	it( 'Pause Donations option submits the duration successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelForm, {
			props: {
				recurringContribution: contribution_mock,
				durationOptions,
				defaultDuration,
				submitPauseRecurringForm: submitPauseFormMock,
				proceedCancelAction: proceedCancelActionMock

			},
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationFormBody = wrapper.find( '#recurring-cancellation-form' );
		const secondDurationOption = cancelDonationFormBody.find( `#option-${ durationOptions[ 1 ].id }` );

		await secondDurationOption.trigger( 'input' );

		const submitButton = cancelDonationFormBody.find( '#submit-pause-action' );
		await submitButton.trigger( 'click' );

		expect( submitPauseFormMock ).toBeCalledWith( `${ durationOptions[ 1 ].value }` );
	} );

	it( 'Cancel Donation confirmation click proceeds with cancel successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelForm, {
			props: {
				recurringContribution: contribution_mock,
				durationOptions,
				defaultDuration,
				submitPauseRecurringForm: submitPauseFormMock,
				proceedCancelAction: proceedCancelActionMock

			},
			global: {
				plugins: [ router ]
			}
		} );

		const cancelDonationFormBody = wrapper.find( '#recurring-cancellation-form' );

		const submitButton = cancelDonationFormBody.find( '#continue' );
		await submitButton.trigger( 'click' );

		expect( proceedCancelActionMock ).toBeCalled();
	} );
} );
