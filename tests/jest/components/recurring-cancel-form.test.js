/* global global describe it expect beforeEach afterEach jest */

const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelForm = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelForm.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

describe( 'Recurring cancel form component', () => {
    const recurringContribution = {
        amount_frequency_key: 'donorportal-recurring-amount-monthly',
        amount_formatted: '$100',
        currency: 'USD',
        payment_method: 'Credit Card: Visa',
        next_sched_contribution_date_formatted: 'September 2, 2025',
        id: 123,
        next_sched_contribution_date: '2025-08-02 00:00:02',
        amount: 10
    };
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
                recurringContribution,
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
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-header' );
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-text' );
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-frequency-annual-switch-alternative-button' );
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-amount-change-alternative-header' );
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-amount-change-alternative-text' );
        expect( cancelDonationFormBody.html() ).toContain( 'donorportal-cancel-recurring-amount-change-alternative-button' );

        // Ensure duration option list are rendered and visible
        const pauseDonationsOptionsList = wrapper.findAll( '#radio-button-options-list' );
        expect( pauseDonationsOptionsList.length ).toBe( 2 );
        expect( cancelDonationFormBody.html() ).toContain( '30 days' );
        expect( cancelDonationFormBody.html() ).toContain( '60 days' );
    } );

    it( 'Pause Donations option submits the duration successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionCancelForm, {
            props: {
                recurringContribution,
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

        const submitButton = cancelDonationFormBody.find( '#option-action' );
        await submitButton.trigger( 'click' );

        expect( submitPauseFormMock ).toBeCalledWith( `${ durationOptions[ 1 ].value }` );
    } );

    it( 'Cancel Donation confirmation click proceeds with cancel successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionCancelForm, {
            props: {
                recurringContribution,
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
