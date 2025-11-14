/* global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionPauseSuccess = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionPauseSuccess.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

describe( 'Recurring pause success component', () => {
	const nextSchedContributionDate = '02 September, 2025';

	it( 'Renders successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionPauseSuccess, {
			props: {
				nextSchedContributionDate
			},
			global: {
				plugins: [ router ]
			}
		} );

		const element = wrapper.find( '#recurring-contribution-pause-success' );
		expect( element.exists() ).toBe( true );

		expect( element.html() ).toContain( `donorportal-pause-recurring-confirmation-subheader:[<strong>${ nextSchedContributionDate }</strong>]` );

		const button = wrapper.findComponent( '#buttonBackToAccount' );
		expect( button.html() ).toContain( 'donorportal-return-to-account-button' );
	} );
} );
