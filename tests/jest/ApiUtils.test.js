/* global jest global describe it expect beforeEach */

const ApiUtils = require( '../../modules/ext.donationInterface.donorPortal/ApiUtils.js' );

describe( 'ApiUtils.requestNewChecksumLink', () => {
	beforeEach( () => {
		global.mw.Api.prototype.post = jest.fn();
	} );

	it( 'includes the subpage param when a subpage is provided', () => {
		ApiUtils.requestNewChecksumLink( 'donor@example.org', 'Special:DonorPortal', 'Home' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email: 'donor@example.org',
			action: 'requestNewChecksumLink',
			page: 'Special:DonorPortal',
			subpage: 'Home'
		} );
	} );

	it( 'omits the subpage param when no subpage is provided', () => {
		ApiUtils.requestNewChecksumLink( 'donor@example.org', 'Special:DonorPortal' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email: 'donor@example.org',
			action: 'requestNewChecksumLink',
			page: 'Special:DonorPortal'
		} );
	} );
} );
