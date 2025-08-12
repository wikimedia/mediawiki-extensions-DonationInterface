/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelSuccess = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelSuccess.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

describe( 'Recurring cancel success component', () => {
    const recurringContribution = {
        amount_frequency_key: 'donorportal-recurring-amount-monthly',
        amount_formatted: '$100',
        currency: 'USD',
        payment_method: 'Credit Card: Visa',
        next_sched_contribution_date_formatted: 'September 2, 2025',
        last_contribution_date_formatted: 'August 2, 2025',
        id: 123,
        next_sched_contribution_date: '2025-08-02 00:00:02',
        amount: 10
    };

    it( 'Renders successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionCancelSuccess, {
            props: {
                recurringContribution
            },
            global: {
                plugins: [ router ]
            }
        } );

        const element = wrapper.find( '#recurring-contribution-cancel-success' );
        expect( element.exists() ).toBe( true );

        expect( element.html() ).toContain( `donorportal-cancel-monthly-recurring-confirmation-text:[${ recurringContribution.amount_frequency_key }:[${ recurringContribution.amount_formatted },${ recurringContribution.currency }]]` );

        const returnToAccountButton = wrapper.findComponent( '#buttonBackToAccount' );
        expect( returnToAccountButton.html() ).toContain( 'donorportal-return-to-account-button' );

        const shareFeedbackButton = wrapper.find( '#shareFeedback' );
        expect( shareFeedbackButton.html() ).toContain( 'donorportal-feedback-button' );
    } );
} );
