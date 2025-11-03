/* global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionSummary = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionSummary.vue' );
const { recurring: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Recurring contribution summary component', () => {

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
		expect( element.html() ).toContain( contribution_mock.next_sched_contribution_date_formatted );
	} );

	it( 'Next contribution date renders N/A when no date is set', () => {
		contribution_mock.next_sched_contribution_date_formatted = null;
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
		const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
			props: {
				recurringContribution: {}
			}
		} );

		const element = wrapper.find( '.contribution-details' );
		expect( element.exists() ).toBe( false );
	} );

	it( 'Extra title render when extraTitle is not empty', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
			props: {
				recurringContribution: contribution_mock,
				extraTitle: 'donorportal-update-recurring-current-donation'
			}
		} );
		const element = wrapper.find( '.contribution-details' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'donorportal-update-recurring-current-donation' );
	} );

	it( 'Render when using lastDonationDate', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
			props: {
				recurringContribution: contribution_mock,
				lastDonationDate: true
			}
		} );
		const element = wrapper.find( '.contribution-details' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( contribution_mock.last_contribution_date_formatted );
	} );

	it( 'Adding extra Class', () => {
		const wrapper = VueTestUtils.shallowMount( RecurringContributionSummary, {
			props: {
				recurringContribution: contribution_mock,
				extraClasses: 'test-class'
			}
		} );
		const element = wrapper.find( '.contribution-details' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( 'box test-class' );
	} );
} );
