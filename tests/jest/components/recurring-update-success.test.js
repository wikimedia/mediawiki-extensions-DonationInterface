/* global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionUpdateSuccess = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionUpdateSuccess.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

describe( 'Recurring update success component', () => {
	const nextSchedContributionDate = '02 September, 2025';
	const newAmount = 'USD 100.00';

	it( 'Renders successfully', async () => {
		const wrapper = VueTestUtils.mount( RecurringContributionUpdateSuccess, {
			props: {
				nextSchedContributionDate,
				newAmount
			},
			global: {
				plugins: [ router ]
			}
		} );

		const element = wrapper.find( '#recurring-contribution-update-success' );
		expect( element.exists() ).toBe( true );

		expect( element.html() ).toContain( `donorportal-update-recurring-confirmation-text:[<strong>${ newAmount }</strong>,<strong>${ nextSchedContributionDate }</strong>]` );

		const returnToAccountButton = wrapper.findComponent( '#buttonBackToAccount' );
		expect( returnToAccountButton.html() ).toContain( 'donorportal-return-to-account-button' );
	} );
} );
