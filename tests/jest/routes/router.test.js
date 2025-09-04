/* global global describe it expect beforeEach afterEach*/
/* eslint-disable es-x/no-promise */

const VueTestUtils = require( '@vue/test-utils' );
const router = require( '../../../modules/ext.donationInterface.donorPortal/router.js' );
const AppComponent = require( '../../../modules/ext.donationInterface.donorPortal/components/App.vue' );
const {  when  } = require( 'jest-when' );

describe( 'Navigation logic', () => {
	beforeEach( () => {
		global.mw.Api.prototype.get.mockReturnValue(
			new Promise( ( resolve, _ ) => {
				resolve( null );
			} )
		);
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {} );
        when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( 'lorem@ipsum.co' );
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
