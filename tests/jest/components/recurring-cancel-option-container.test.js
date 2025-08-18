/* global global describe it expect beforeEach afterEach jest*/
const VueTestUtils = require( '@vue/test-utils' );
const RecurringContributionCancelAltOptionContainer = require( '../../../modules/ext.donationInterface.donorPortal/components/RecurringContributionCancelAltOptionContainer.vue' );

describe( 'Recurring cancel option component', () => {
    const header = 'Header text',
    text = 'Sub text',
    buttonText = 'Button text',
    actionMock = jest.fn();

    it( 'Renders successfully', async () => {
        const wrapper = VueTestUtils.mount( RecurringContributionCancelAltOptionContainer, {
            props: {
                header,
                text,
                buttonText,
                action: actionMock
            }
        } );

        const element = wrapper.find( '#donorportal-cancel-alt-option' );
        expect( element.exists() ).toBe( true );

        expect( element.html() ).toContain( header );
        expect( element.html() ).toContain( text );
        expect( element.html() ).toContain( buttonText );

        const actionButton = wrapper.find( '#option-action' );
        await actionButton.trigger( 'click' );
        expect( actionMock ).toBeCalled();
    } );
} );
