/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const DonorContactDetails = require( '../../../modules/ext.donationInterface.donorPortal/components/DonorContactDetails.vue' );
const contact_details_mock = require( '../mocks/contact_details.mock.js' );

describe( 'Donor contact details component', () => {

    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( DonorContactDetails, {
            props: contact_details_mock
        } );

        const element = wrapper.find( '.dp-card' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( contact_details_mock.name );
        expect( element.html() ).toContain( contact_details_mock.id );
        expect( element.html() ).toContain( contact_details_mock.address.street_address );
        expect( element.html() ).toContain( contact_details_mock.address.city );
        expect( element.html() ).toContain( contact_details_mock.address.state_province );
        expect( element.html() ).toContain( contact_details_mock.address.postal_code );
        expect( element.html() ).toContain( contact_details_mock.address.country );
    } );

} );
