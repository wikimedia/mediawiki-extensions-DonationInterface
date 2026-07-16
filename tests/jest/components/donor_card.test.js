/* global describe it expect */

const VueTestUtils = require( '@vue/test-utils' );
const DonorCardComponent = require(
	'../../../modules/ext.donationInterface.donorPortal/components/DonorCardComponent.vue' );
const RecurringContribution = require(
	'../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionComponent.vue' );
const OnetimeContribution = require(
	'../../../modules/ext.donationInterface.donorPortal/components/OnetimeContribution.vue' );

describe( 'Donor Card Component', () => {
	it( 'Renders the recurring card with active contributions', () => {
		const wrapper = VueTestUtils.shallowMount( DonorCardComponent, {
			props: {
				activeRecurringContributions: [ { id: '1' }, { id: '2' } ]
			}
		} );

		const card = wrapper.find( '.dp-card' );
		expect( card.exists() ).toBe( true );
		expect( card.text() ).toContain( 'donorportal-recurring-heading' );

		const contributions = wrapper.findAllComponents( RecurringContribution );
		expect( contributions.length ).toBe( 2 );
		expect( contributions[ 0 ].props( 'isActive' ) ).toBe( true );
		// The one-time card must not render when recurring contributions exist.
		expect( wrapper.findComponent( OnetimeContribution ).exists() ).toBe( false );
	} );

	it( 'Renders the recurring card when only inactive contributions exist', () => {
		const wrapper = VueTestUtils.shallowMount( DonorCardComponent, {
			props: {
				inactiveRecurringContributions: [ { id: '1' } ]
			}
		} );

		const card = wrapper.find( '.dp-card' );
		expect( card.exists() ).toBe( true );
		expect( card.text() ).toContain( 'donorportal-recurring-heading' );

		const contributions = wrapper.findAllComponents( RecurringContribution );
		expect( contributions.length ).toBe( 1 );
		expect( contributions[ 0 ].props( 'isActive' ) ).toBe( false );
	} );

	it( 'Renders the one-time card when there are no recurring contributions', () => {
		const wrapper = VueTestUtils.shallowMount( DonorCardComponent, {
			props: {
				onetimeContribution: { id: '1' }
			}
		} );

		const card = wrapper.find( '.dp-card' );
		expect( card.exists() ).toBe( true );
		expect( card.text() ).toContain( 'donorportal-most-recent-donation' );
		expect( wrapper.findComponent( OnetimeContribution ).exists() ).toBe( true );
		expect( wrapper.findAllComponents( RecurringContribution ).length ).toBe( 0 );
	} );

	it( 'Renders nothing when there are no contributions at all', () => {
		const wrapper = VueTestUtils.shallowMount( DonorCardComponent );

		// Outer section is guarded by v-if, so the card is absent entirely.
		expect( wrapper.find( '.dp-card' ).exists() ).toBe( false );
	} );
} );
