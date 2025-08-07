/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionSummary = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionSummary.vue' );

describe( 'Recurring contribution summary component', () => {
    const contribution_mock = {
        amount_frequency_key: 'donorportal-recurring-amount-monthly',
        amount_formatted: '$100',
        currency: 'USD',
        payment_method: 'Credit Card: Visa',
        last_contribution_date_formatted: '02 September, 2025',
        id: 123
    };
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
            props: {
                recurringContribution: contribution_mock
            }
        } );

        const element = wrapper.find( '.contribution-details' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( contribution_mock.amount_frequency_key );
        expect( element.html() ).toContain( contribution_mock.amount_formatted );
        expect( element.html() ).toContain( contribution_mock.currency );
        expect( element.html() ).toContain( contribution_mock.payment_method );
        expect( element.html() ).toContain( contribution_mock.last_contribution_date_formatted );
    } );

    it( 'Last contribution date renders N/A when no date is set', () => {
        contribution_mock.last_contribution_date_formatted = null;
        const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
            props: {
                recurringContribution: contribution_mock
            }
        } );

        const element = wrapper.find( '.contribution-details' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( contribution_mock.amount_frequency_key );
        expect( element.html() ).toContain( contribution_mock.amount_formatted );
        expect( element.html() ).toContain( contribution_mock.currency );
        expect( element.html() ).toContain( contribution_mock.payment_method );
        expect( element.html() ).toContain( 'N/A' );
    } );

    it( 'Does not render when prop is not set', () => {
        contribution_mock.last_contribution_date_formatted = null;
        const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
            props: {
                recurringContribution: {}
            }
        } );

        const element = wrapper.find( '.contribution-details' );
        expect( element.exists() ).toBe( false );
    } );
} );
