/* global global jest describe it expect beforeEach afterEach*/

const { when } = require( 'jest-when' );
const VueTestUtils = require( '@vue/test-utils' );
const HeaderComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/Header.vue' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );

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
		const wrapper = VueTestUtils.mount( HeaderComponent, {
			global: { plugins: [ router ] }
		} );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		const listItemElements = listElement.findAll( 'li' );
		expect( listItemElements.length ).toBe( 7 );
		expect( listElement.html() ).toContain( 'donorportal-header-my-portal' );
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

	it( 'Hides logged in links when showLogin is set', () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( { showLogin: true } );
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		const listItemElements = listElement.findAll( 'li' );
		expect( listItemElements.length ).toBe( 4 );
	} );

	it( 'Hides logged in links when donorData has an error', () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( { error: 'lookup-failed' } );
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		const listItemElements = listElement.findAll( 'li' );
		expect( listItemElements.length ).toBe( 4 );
	} );

	it( 'Requests logout and builds the login URL when logout link is clicked', () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {
			contact_id: '12345',
			checksum: 'random-checksum'
		} );
		// Start on the portal page so the redirect is a fragment change, which is
		// the only kind of navigation jsdom will perform. This avoids a jsdom "Not implemented: navigation"
		// error in the stack trace when testing.
		window.history.replaceState( {}, '', '/wiki/Special:DonorPortal' );
		global.mw.util.getUrl.mockReturnValue( '/wiki/Special:DonorPortal' );
		// always() runs the redirect callback immediately
		const always = jest.fn( ( callback ) => callback() );
		global.mw.Api.prototype.post.mockReturnValue( { always } );
		const wrapper = VueTestUtils.shallowMount( HeaderComponent );

		const listElement = wrapper.find( { ref: 'nav__links' } );
		const linkElements = listElement.findAll( 'a' );
		const logoutLink = linkElements[ linkElements.length - 1 ];
		expect( logoutLink.text() ).toBe( 'donorportal-header-logout' );

		logoutLink.trigger( 'click' );

		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			action: 'requestLogout',
			contact_id: 12345,
			checksum: 'random-checksum'
		} );
		expect( global.mw.util.getUrl ).toHaveBeenCalledWith( 'Special:DonorPortal' );
	} );
} );
