/* global global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const OnetimeContribution = require( '../../../modules/ext.donationInterface.donorPortal/components/OnetimeContribution.vue' );
const { onetime: contribution_mock } = require( '../mocks/contribution_mock.mock.js' );

describe( 'Donor contact details component', () => {
    it( 'Renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( OnetimeContribution, {
            props: {
                contribution: contribution_mock
            }
        } );

        const element = wrapper.find( '.donorportal-recent-donation' );
        expect( element.exists() ).toBe( true );
        expect( element.html() ).toContain( contribution_mock.last_amount_formatted );
        expect( element.html() ).toContain( contribution_mock.last_currency );
        expect( element.html() ).toContain( contribution_mock.last_payment_method );
        expect( element.html() ).toContain( contribution_mock.last_receive_date_formatted );
    } );

} );
