/* global describe it expect beforeEach afterEach jest */
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelConfirmation = require(
	'../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelConfirmation.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring cancel confirmation component', () => {
	const submitCancelRecurringFormMock = jest.fn();

	it( 'Renders successfully', () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelConfirmation, {
			props: {
				recurringContribution: contribution_mock,
				submitCancelRecurringForm: submitCancelRecurringFormMock
			},
			global: {
				plugins: [ router ]
			}
		} );

		const element = wrapper.find( '#recurring-cancellation-confirmation' );
		expect( element.exists() ).toBe( true );

		expect( element.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-header' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-confirmation-request-text' );
		expect( element.html() ).toContain( contribution_mock.amount_frequency_key );
		expect( element.html() ).toContain( contribution_mock.payment_method );
		expect( element.html() ).toContain( contribution_mock.last_contribution_date_formatted );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-request-for-reason' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-cancel-button' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-changed-my-mind' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-switch-to-annual' );

		// Ensure reason option list are rendered and visible
		const cancelReasonOptionsList = wrapper.findAll( '#radio-button-options-list' );
		expect( cancelReasonOptionsList.length ).toBe( 6 );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-financial' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-donation-frequency' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-prefer-other-methods' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-cancel-support' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-unintended' );
		expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-other' );
	} );

	it( 'Submits the selected recurring reason on button click', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelConfirmation, {
			props: {
				recurringContribution: contribution_mock,
				submitCancelRecurringForm: submitCancelRecurringFormMock
			},
			global: {
				plugins: [ router ]
			}
		} );

		const element = wrapper.find( '#recurring-cancellation-confirmation' );
		const givingMethodReason = element.find( '#option-giving-method' );
		await givingMethodReason.trigger( 'input' );

		const submitButton = element.find( '#continue' );
		await submitButton.trigger( 'click' );

		expect( submitCancelRecurringFormMock ).toBeCalledWith( 'Update' );
	} );

	it( 'Disables the confirmation submit when no option is selected', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionCancelConfirmation, {
			props: {
				recurringContribution: contribution_mock,
				submitCancelRecurringForm: submitCancelRecurringFormMock
			},
			global: {
				plugins: [ router ]
			}
		} );

		const cancelConfirmation = wrapper.find( '#recurring-cancellation-confirmation' );

		// Check the confirmation submit to confirm its disabled
		const confirmSubmit = cancelConfirmation.find( '#continue' );
		expect( confirmSubmit.element.disabled ).toBe( true );

		// Select an option
		const givingMethodReason = cancelConfirmation.find( '#option-giving-method' );
		await givingMethodReason.trigger( 'input' );

		// Check the confirmation submit button to confirm its enabled
		expect( confirmSubmit.element.disabled ).toBe( false );
	} );
} );
