/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const InactiveRecurringContribution = require( '../../../modules/ext.donationInterface.donorPortal/components/InactiveRecurringContribution.vue' );

describe( 'Donor contact details component', () => {
    const contribution_mock = {
        amount_frequency_key: 'donorportal-recurring-amount-monthly',
        amount_formatted: '$100',
        currency: 'USD',
        payment_method: 'Credit Card: Visa',
        last_contribution_date_formatted: 'September 2, 2025',
        restart_key: 'donorportal-restart-monthly',
        hasLastContribution: true,
        id: '125'
    };
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( InactiveRecurringContribution, {
            props: {
                contribution: contribution_mock
            }
        } );

        const element = wrapper.find( '.donorportal-inactive-recurring' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( 'donorportal-last-amount-and-date' );
        expect( element.html() ).toContain( contribution_mock.amount_formatted );
        expect( element.html() ).toContain( contribution_mock.currency );
        expect( element.html() ).toContain( contribution_mock.payment_method );
        expect( element.html() ).toContain( contribution_mock.last_contribution_date_formatted );
        expect( element.html() ).toContain( contribution_mock.restart_key );

    } );
    it( 'Only adds last contribution if there if there is any on record', () => {
        const wrapper = VueTestUtils.shallowMount( InactiveRecurringContribution, {
            props: {
                contribution: Object.assign( contribution_mock, {
                    hasLastContribution: false
                } )
            }
        } );

        const element = wrapper.find( '.donorportal-inactive-recurring' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).not.toContain( 'donorportal-last-amount-and-date' );

    } );
} );
