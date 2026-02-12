/* global global describe it expect beforeEach afterEach*/
/* eslint-disable es-x/no-promise */

const VueTestUtils = require( '@vue/test-utils' );
const LoginView = require( '../../../modules/ext.donationInterface.donorPortal/views/LoginView.vue' );
const { when } = require( 'jest-when' );
const helpEmail = 'lorem@ipsum.co';

describe( 'Login view', () => {
	beforeEach( () => {
		global.mw.Api.prototype.get.mockReturnValue(
			new Promise( ( resolve, _ ) => {
				resolve( null );
			} )
		);
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {} );
		when( global.mw.config.get ).calledWith( 'help_email' ).mockReturnValue( helpEmail );
	} );

	afterEach( () => {
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( resolve, _ ) => {
				resolve( {} );
			} )
		);
	} );

	it( 'Login view renders successfully', () => {
		const wrapper = VueTestUtils.mount( LoginView );

		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );
		expect( loginBody.html() ).toContain( 'donorportal-login-header' );
		expect( loginBody.html() ).toContain( 'donorportal-login-text' );
		expect( loginBody.html() ).toContain( 'emailpreferences-send-new-link' );
		expect( loginBody.html() ).toContain( 'donorportal-login-email-placeholder' );
		expect( loginBody.html() ).toContain( 'donorportal-loginpage-figure-caption' );
		expect( loginBody.html() ).toContain( 'donorportal-loginpage-figure-alt' );

		const linkSentText = wrapper.find( '#link-sent-text' );
		expect( linkSentText.isVisible() ).toBe( false );
	} );

	it( 'Api request fails on empty input', async () => {
		const wrapper = VueTestUtils.mount( LoginView );
		// Fake an API response
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( _, reject ) => {
				reject( 'missingparam' );
			} )
		);

		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );

		const errorMessageText = wrapper.find( '#error-message-text' );
		expect( errorMessageText.isVisible() ).not.toBe( true );

		const donorEmailInput = wrapper.find( '#new-checksum-link-email' );
		expect( donorEmailInput.exists() ).toBe( true );
		// Enabled input because no server error
		expect( donorEmailInput.attributes() ).not.toHaveProperty( 'disabled' );

		const requestLinkButton = wrapper.find( '#request-link-button' );
		expect( requestLinkButton.exists() ).toBe( true );
		// Enabled button because no server error
		expect( requestLinkButton.attributes() ).not.toHaveProperty( 'disabled' );
		await requestLinkButton.trigger( 'click' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email: '',
			action: 'requestNewChecksumLink',
			page: mw.config.get( 'requestNewChecksumPage' )
		} );

		expect( errorMessageText.isVisible() ).toBe( true );
		expect( errorMessageText.html() ).toContain( 'donorportal-email-required' );
	} );

	it( 'Api request fails on invalid input', async () => {
		const wrapper = VueTestUtils.mount( LoginView );
		// Fake an API response
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( _, reject ) => {
				reject( 'Invalid input params' );
			} )
		);

		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );

		const errorMessageText = wrapper.find( '#error-message-text' );
		expect( errorMessageText.isVisible() ).not.toBe( true );

		const donorEmailInput = wrapper.find( '#new-checksum-link-email' );
		expect( donorEmailInput.exists() ).toBe( true );

		const requestLinkButton = wrapper.find( '#request-link-button' );
		expect( requestLinkButton.exists() ).toBe( true );
		await requestLinkButton.trigger( 'click' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email: '',
			action: 'requestNewChecksumLink',
			page: mw.config.get( 'requestNewChecksumPage' )
		} );

		expect( errorMessageText.isVisible() ).toBe( true );
		expect( errorMessageText.html() ).toContain( 'donorportal-something-wrong' );
	} );

	it( 'Input is cleared when typing after API error', async () => {
		const wrapper = VueTestUtils.mount( LoginView );
		// Fake an API response
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( resolve, reject ) => {
				reject( 'Invalid input params' );
			} )
		);

		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );

		const errorMessageText = wrapper.find( '#error-message-text' );
		expect( errorMessageText.isVisible() ).not.toBe( true );

		const donorEmailInput = wrapper.find( '#new-checksum-link-email' );
		expect( donorEmailInput.exists() ).toBe( true );

		const requestLinkButton = wrapper.find( '#request-link-button' );
		expect( requestLinkButton.exists() ).toBe( true );
		await requestLinkButton.trigger( 'click' );
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email: '',
			action: 'requestNewChecksumLink',
			page: mw.config.get( 'requestNewChecksumPage' )
		} );

		expect( errorMessageText.isVisible() ).toBe( true );
		expect( errorMessageText.html() ).toContain( 'donorportal-something-wrong' );

		await donorEmailInput.setValue( 'jwales' );
		expect( errorMessageText.isVisible() ).not.toBe( true );
	} );

	it( 'Api request is successful on valid input', async () => {
		const wrapper = VueTestUtils.mount( LoginView );
		// Fake an API response
		global.mw.Api.prototype.post.mockReturnValue(
			new Promise( ( resolve, reject ) => {
				resolve( {} );
			} )
		);
		const email = 'jwales@example.com';

		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );

		const donorEmailInput = wrapper.find( '#new-checksum-link-email' );
		expect( donorEmailInput.exists() ).toBe( true );

		const successMessageText = wrapper.find( '#link-sent-text' );
		expect( successMessageText.isVisible() ).toBe( false );

		const errorMessageText = wrapper.find( '#error-message-text' );
		expect( errorMessageText.isVisible() ).not.toBe( true );

		// set email in input
		await donorEmailInput.setValue( email );

		// assert that the state value is set to the input value
		expect( wrapper.vm.donorEmail ).toBe( email );
		const requestLinkButton = wrapper.find( '#request-link-button' );
		expect( requestLinkButton.exists() ).toBe( true );
		await requestLinkButton.trigger( 'click' );

		// assert api call on button click
		expect( global.mw.Api.prototype.post ).toHaveBeenCalledWith( {
			email,
			action: 'requestNewChecksumLink',
			page: mw.config.get( 'requestNewChecksumPage' )
		} );

		expect( errorMessageText.isVisible() ).not.toBe( true );
		expect( successMessageText.isVisible() ).toBe( true );
		expect( successMessageText.html() ).toContain( 'emailpreferences-new-link-sent' );
	} );

	it( 'Page shows error text and disable input on CiviProxy error', async () => {
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReset();

		// Mock server error values
		when( global.mw.config.get ).calledWith( 'donorData' ).mockReturnValue( {
			showLogin: true,
			hasError: true
		} );

		const wrapper = VueTestUtils.mount( LoginView );
		const loginBody = wrapper.find( '.auth' );
		expect( loginBody.exists() ).toBe( true );

		const donorEmailInput = wrapper.find( '#new-checksum-link-email' );
		expect( donorEmailInput.exists() ).toBe( true );

		const successMessageText = wrapper.find( '#link-sent-text' );
		expect( successMessageText.isVisible() ).toBe( false );

		const errorMessageText = wrapper.find( '#error-message-text' );
		expect( errorMessageText.isVisible() ).not.toBe( true );

		const serverErrorMessageText = wrapper.find( '#server-error-message-text' );
		expect( serverErrorMessageText.isVisible() ).toBe( true );
		expect( serverErrorMessageText.html() ).toContain( `donorportal-authentication-failure:[<a href="mailto:${ helpEmail }?subject=donorportal-login-problems">donorportal-update-donation-donor-relations-team</a>]` );

		// Disabled input because of the server error
		expect( donorEmailInput.attributes() ).toHaveProperty( 'disabled' );

		// Disabled button because of the server error
		const requestLinkButton = wrapper.find( '#request-link-button' );
		expect( requestLinkButton.exists() ).toBe( true );
		expect( requestLinkButton.attributes() ).toHaveProperty( 'disabled' );

		await requestLinkButton.trigger( 'click' );

		// assert no api call on button click
		expect( global.mw.Api.prototype.post ).not.toHaveBeenCalledWith( {
			email: '',
			action: 'requestNewChecksumLink',
			page: mw.config.get( 'requestNewChecksumPage' )
		} );

		expect( errorMessageText.isVisible() ).not.toBe( true );
		expect( successMessageText.isVisible() ).not.toBe( true );
	} );
} );
