/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelSuccess = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelSuccess.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring cancel success component', () => {
    it( 'Renders successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionCancelSuccess, {
            props: {
                recurringContribution: contribution_mock
            },
            global: {
                plugins: [ router ]
            }
        } );

        const element = wrapper.find( '#recurring-contribution-cancel-success' );
        expect( element.exists() ).toBe( true );

        expect( element.html() ).toContain( `donorportal-cancel-monthly-recurring-confirmation-text:[${ contribution_mock.amount_frequency_key }:[${ contribution_mock.amount_formatted },${ contribution_mock.currency }]]` );

        const returnToAccountButton = wrapper.findComponent( '#buttonBackToAccount' );
        expect( returnToAccountButton.html() ).toContain( 'donorportal-return-to-account-button' );

        const shareFeedbackButton = wrapper.find( '#shareFeedback' );
        expect( shareFeedbackButton.html() ).toContain( 'donorportal-feedback-button' );
    } );
} );
