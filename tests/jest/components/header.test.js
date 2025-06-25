const VueTestUtils = require( '@vue/test-utils' );
const HeaderComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/Header.vue' );

describe( 'Header Component', () => {
    it( 'Logo image renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( HeaderComponent );

        const element = wrapper.find( '.nav-logo__image' );
        expect( element.exists() ).toBe( true );
    } );

    it( 'Menu container and items renders successfully', () => {
        const wrapper = VueTestUtils.shallowMount( HeaderComponent );

        const listElement = wrapper.find( '#menu-header-menu' );
        expect( listElement.exists() ).toBe( true );
        const listItemElements = wrapper.findAll( '.menu-item' );
        expect( listItemElements.length ).toBe( 8 );
    } );
} );
