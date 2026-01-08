/* global global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionComponent.vue' );
const { recurring: recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { inactive_recurring: inactive_recurring_mock } = require( '../mocks/contribution_mock.mock.js' );
const { when } = require( 'jest-when' );
const DonorDataMock = require( '../mocks/donor_data.mock.js' );

describe( 'Active recurring contribution test', () => {
	beforeEach( () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( DonorDataMock );
		when( global.mw.config.get ).calledWith( 'newDonationUrl' ).mockReturnValue( 'http://donate.test' );
	} );
	it( 'Active recurring contribution renders successfully', () => {
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
		expect( element.html() ).toContain( `<a href="#/cancel-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-cancel </a>` );
		expect( element.html() ).toContain( `<a target="_self" href="#/update-donations/${ recurring_mock.id }"` );
		expect( element.html() ).toContain( `<a href="#/pause-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-pause </a>` );
	} );

	it( 'Donor contact details component renders successfully', () => {
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
		expect( element.html() ).toContain( '<a target="_blank" href="http://donate.test' );
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
        expect( element.html() ).toContain( '<a target="_blank" href="http://donate.test' );
    } );
	it( 'Render is processing status correctly', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionComponent, {
			props: {
				contribution: Object.assign( recurring_mock, {
					is_processing: true
				} ),
				isActive: true
			}
		} );

		const element = wrapper.find( '.is-processing' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'donorportal-processing' );
		expect( element.html() ).not.toContain( `<a href="#/cancel-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-cancel </a>` );
		expect( element.html() ).not.toContain( `<a target="_self" href="#/update-donations/${ recurring_mock.id }"` );
		expect( element.html() ).not.toContain( `<a href="#/pause-donations/${ recurring_mock.id }" class="link"> donorportal-recurring-pause </a>` );
	} );
} );
