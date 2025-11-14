/* global describe it expect beforeEach afterEach*/
const VueTestUtils = require( '@vue/test-utils' );
const ErrorComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/ErrorComponent.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

describe( 'Error component', () => {
	const helpEmail = 'help@example.com';

	it( 'Renders successfully', async () => {
		const wrapper = VueTestUtils.mount( ErrorComponent, {
			props: {
				failureMessage: `donorportal-pause-failure:[${ helpEmail }]`
			},
			global: {
				plugins: [ router ]
			}
		} );

		const element = wrapper.find( '#error-component' );
		expect( element.exists() ).toBe( true );

		expect( element.html() ).toContain( `donorportal-pause-failure:[${ helpEmail }]` );
	} );
} );
