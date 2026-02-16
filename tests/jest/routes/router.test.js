/* global jest global describe it expect beforeEach afterEach*/

const VueTestUtils = require( '@vue/test-utils' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const AppComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/App.vue' );
const { when } = require( 'jest-when' );

describe( 'Navigation logic', () => {
	// Mock api async methods as jQuery promises so jQuery promise chain methods like ("always") are executed in tests.
	const jQuery = jest.requireActual( '../../../resources/lib/jquery/jquery.js' );
	beforeEach( () => {
		global.mw.Api.prototype.get = jest.fn( () => jQuery.Deferred().resolve().promise() );
		global.mw.Api.prototype.post = jest.fn( () => jQuery.Deferred().resolve( { result: { } } ).promise() );
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {} );
		when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'lorem@ipsum.co' );
		when( global.mw.config.get ).calledWith( 'emailPreferencesUrl' ).mockReturnValue( 'https://emailprefs.wiki' );
	} );

	it( 'Login screen renders successfully when required during navigation', async () => {
		when( global.mw.config.get ).calledWith( 'showRequestNewChecksumModal' ).mockReturnValue( true );
		await router.push( '/' );

		await router.isReady();
		const wrapper = VueTestUtils.mount( AppComponent, {
			data() {
				return {};
			},
			global: {
				plugins: [ router ]
			}
		} );

		expect( wrapper.html() ).toContain( 'auth' );
	} );

	it( 'Home screen renders successfully when checksum is valid', async () => {
		when( global.mw.config.get ).calledWith( 'showRequestNewChecksumModal' ).mockReturnValue( false );
		await router.push( '/' );

		await router.isReady();
		const wrapper = VueTestUtils.mount( AppComponent, {
			data() {
				return {};
			},
			global: {
				plugins: [ router ]
			}
		} );

		expect( wrapper.html() ).toContain( 'container dp-dashboard' );
	} );

} );
