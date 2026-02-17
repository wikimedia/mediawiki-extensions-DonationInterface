/* global global describe it expect beforeEach afterEach*/

const { when } = require( 'jest-when' );
const VueTestUtils = require( '@vue/test-utils' );
const HeaderComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/Header.vue' );

describe( 'Header Component', () => {
	it( 'Logo image renders successfully', () => {
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const element = wrapper.find( '.cdx-icon' );
		expect( element.exists() ).toBe( true );
	} );

	it( 'Menu container and items renders successfully when logged out', () => {
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		expect( listElement.exists() ).toBe( true );
		const listItemElements = listElement.findAll( 'li' );
		expect( listItemElements.length ).toBe( 4 );
	} );

	it( 'Shows logout link when logged in', () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {} );
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		const listItemElements = listElement.findAll( 'li' );
		expect( listItemElements.length ).toBe( 7 );
	} );

	it( 'Menu button toggles class on button and list', () => {
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const toggleMenuButton = wrapper.find( { ref: 'nav__toggle' } );
		const menuListElement = wrapper.find( { ref: 'nav__links' } );
		expect( toggleMenuButton.exists() ).toBe( true );
		expect( toggleMenuButton.classes() ).not.toContain( 'active' );
		expect( menuListElement.classes() ).not.toContain( 'active' );

		toggleMenuButton.trigger( 'click' );
		expect( toggleMenuButton.classes() ).toContain( 'active' );
		expect( menuListElement.classes() ).toContain( 'active' );
	} );
} );
