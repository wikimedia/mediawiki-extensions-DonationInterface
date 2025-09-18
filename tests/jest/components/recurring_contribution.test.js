/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionComponent.vue' );
const { recurring: recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { inactive_recurring: inactive_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Active recurring contribution test', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
            props: {
                contribution: recurring_mock,
                isActive: true
            }
        } );

        const element = wrapper.find( '.is-recurring' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( recurring_mock.amount_frequency_key );
        expect( element.html() ).toContain( recurring_mock.amount_formatted );
        expect( element.html() ).toContain( recurring_mock.currency );
        expect( element.html() ).toContain( recurring_mock.payment_method );
        expect( element.html() ).toContain( recurring_mock.next_sched_contribution_date_formatted );
        expect( element.html() ).toContain( `<a href="cancel-donations/${   recurring_mock.id   }" target="_blank" class="link"> donorportal-recurring-cancel </a>` );
        expect( element.html() ).toContain( `<a href="pause-donations/${  recurring_mock.id  }" target="_blank" class="link"> donorportal-recurring-pause </a>` );
    } );

} );

describe( 'Donor contact details component', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
            props: {
                contribution: inactive_recurring_mock,
                isActive: false
            }
        } );

        const element = wrapper.find( '.is-lapsed' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( 'donorportal-last-amount-and-date' );
        expect( element.html() ).toContain( inactive_recurring_mock.amount_formatted );
        expect( element.html() ).toContain( inactive_recurring_mock.currency );
        expect( element.html() ).not.toContain( inactive_recurring_mock.payment_method );
        expect( element.html() ).toContain( inactive_recurring_mock.last_contribution_date_formatted );
        expect( element.html() ).toContain( inactive_recurring_mock.restart_key );

    } );
    it( 'Only adds last contribution if there if there is any on record', () => {
        const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
            props: {
                contribution: Object.assign( inactive_recurring_mock, {
                    hasLastContribution: false
                } ),
                isActive: false
            }
        } );

        const element = wrapper.find( '.is-lapsed' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).not.toContain( 'donorportal-last-amount-and-date' );

    } );
} );
