/* global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const GreetingComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/GreetingComponent.vue' );
const contact_details_mock = require( '../mocks/contact_details.mock.js' );

describe( 'Donor contact details component', () => {
	it( 'Renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( GreetingComponent, {
			props: {
				name: contact_details_mock.name
			}
		} );

		const element = wrapper.find( '.dp-dashboard__intro' );
		expect( element.exists() ).toBe( true );
		expect( element.html() ).toContain( `donorportal-greeting:[${ contact_details_mock.name }]` );
		expect( element.html() ).toContain( 'donorportal-greetingtext' );

	} );

} );
