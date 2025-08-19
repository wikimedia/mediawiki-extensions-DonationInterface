/* global global describe it expect beforeEach afterEach jest */
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelConfirmation = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelConfirmation.vue' );
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
        expect( element.html() ).toContain( 'donorportal-cancel-recurring-reason-supporting-others' );
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

        expect( submitCancelRecurringFormMock ).toBeCalledWith( 'Giving method' );
    } );

    it( 'Disables the recurring input when an option other than "Other" is selected', async () => {
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

        // Select an option that is not other
        const givingMethodReason = cancelConfirmation.find( '#option-giving-method' );
        await givingMethodReason.trigger( 'input' );

        // Check the other reason input field to confirm its disabled
        let otherOptionCustomInput = cancelConfirmation.find( '#other-reason' );
        expect( otherOptionCustomInput.element.disabled ).toBe( true );

        // Select the "other" option
        const otherReason = cancelConfirmation.find( '#option-other' );
        await otherReason.trigger( 'input' );

        // Check the other input field to confirm its enabled
        otherOptionCustomInput = cancelConfirmation.find( '#other-reason' );
        expect( otherOptionCustomInput.element.disabled ).toBe( false );

        // Select the any other option that is not the "other" option
        const cancelSupportReason = cancelConfirmation.find( '#option-cancel-support' );
        await cancelSupportReason.trigger( 'input' );

        // Expect custom input to be back to disabled
        otherOptionCustomInput = cancelConfirmation.find( '#other-reason' );
        expect( otherOptionCustomInput.element.disabled ).toBe( true );
    } );

    it( 'Attempts to submit the value entered into the custom input when other option is selected', async () => {
        const customReason = 'Custom reason';

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

        // Select the "other" option
        const otherReason = cancelConfirmation.find( '#option-other' );
        await otherReason.trigger( 'input' );

        // Check the other input field to confirm its enabled
        const otherOptionCustomInput = cancelConfirmation.find( '#other-reason' );
        expect( otherOptionCustomInput.element.disabled ).toBe( false );
        otherOptionCustomInput.element.value = customReason;
        await otherOptionCustomInput.trigger( 'input' );
        await VueTestUtils.flushPromises();

        const submitButton = cancelConfirmation.find( '#continue' );
        await submitButton.trigger( 'click' );
        await VueTestUtils.flushPromises();

        expect( submitCancelRecurringFormMock ).toBeCalledWith( customReason );
    } );
} );
