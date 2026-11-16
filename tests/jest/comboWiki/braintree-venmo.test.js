/* global describe it expect jest beforeEach global */
jest.mock( 'ext.donationInterface.walletFallback', () => ( {
	hasValidWalletName: jest.fn(),
	hasValidWalletEmail: jest.fn(),
	showWalletFallbackFields: jest.fn()
} ) );

const VueTestUtils = require( '@vue/test-utils' );
const BraintreeVenmo = require( '../../../modules/ext.donationInterface.comboWiki/components/BraintreeVenmo.vue' );
const walletFallback = require( 'ext.donationInterface.walletFallback' );

const venmo = { tokenize: jest.fn() };
window.braintree = {
	client: { create: jest.fn() },
	dataCollector: { create: jest.fn() },
	venmo: { create: jest.fn() }
};

const TOKENIZED = {
	nonce: 'fake-venmo-nonce',
	details: {
		username: 'venmouser',
		paymentContextId: 'ctx-123',
		firstName: 'Ven',
		lastName: 'Mo',
		email: 'donor@example.org',
		payerInfo: {
			firstName: 'Ven',
			lastName: 'Mo',
			email: 'donor@example.org',
			phoneNumber: '555-1234',
			shippingAddress: '123 Main St',
			externalId: 'cust-456'
		}
	}
};

/**
 * @param {Object} [props] Overrides for the donation and donorDetails props
 * @return {Object} The mounted component, once its setup in onMounted has finished
 */
async function mountVenmo( props = {} ) {
	const wrapper = VueTestUtils.mount( BraintreeVenmo, {
		props: Object.assign( {
			donation: { frequency: 'once', amount: 10 },
			donorDetails: null
		}, props ),
		global: {
			provide: {
				params: { braintreeConfiguration: { clientToken: 'client-token', scripts: [ 'client.js' ] } }
			}
		}
	} );
	await VueTestUtils.flushPromises();
	return wrapper;
}

beforeEach( () => {
	window.mw.loader = { getScript: jest.fn( () => Promise.resolve() ) };
	window.braintree.client.create.mockResolvedValue( {} );
	window.braintree.dataCollector.create.mockResolvedValue( { deviceData: 'device-data' } );
	window.braintree.venmo.create.mockResolvedValue( venmo );
	venmo.tokenize.mockResolvedValue( TOKENIZED );
	walletFallback.hasValidWalletName.mockReturnValue( true );
	walletFallback.hasValidWalletEmail.mockReturnValue( true );
	walletFallback.showWalletFallbackFields.mockReset();
} );

describe( 'ComboWiki Braintree Venmo setup', () => {
	it( 'shows the button after Braintree initializes', async () => {
		const wrapper = await mountVenmo();
		expect( wrapper.find( 'button' ).isVisible() ).toBe( true );
	} );

	it( 'creates the Venmo instance with the correct options for a one-time donation', async () => {
		await mountVenmo( { donation: { frequency: 'once', amount: 25 } } );
		expect( window.braintree.venmo.create ).toHaveBeenCalledWith( expect.objectContaining( {
			allowDesktop: true,
			mobileWebFallBack: true,
			allowNewBrowserTab: true,
			allowDesktopWebLogin: false,
			paymentMethodUsage: 'single_use',
			totalAmount: '25'
		} ) );
	} );

	it( 'uses multi_use for monthly donations', async () => {
		await mountVenmo( { donation: { frequency: 'month', amount: 5 } } );
		expect( window.braintree.venmo.create ).toHaveBeenCalledWith(
			expect.objectContaining( { paymentMethodUsage: 'multi_use' } )
		);
	} );

	it( 'uses multi_use for yearly donations', async () => {
		await mountVenmo( { donation: { frequency: 'year', amount: 100 } } );
		expect( window.braintree.venmo.create ).toHaveBeenCalledWith(
			expect.objectContaining( { paymentMethodUsage: 'multi_use' } )
		);
	} );

	it( 'emits an error when setup fails', async () => {
		const error = new Error( 'Braintree init failed' );
		window.braintree.venmo.create.mockRejectedValue( error );
		const wrapper = await mountVenmo();
		expect( wrapper.emitted( 'error' ) ).toEqual( [ [ error ] ] );
		expect( wrapper.find( 'button' ).isVisible() ).toBe( false );
	} );
} );

describe( 'ComboWiki Braintree Venmo when the donor clicks the Venmo button', () => {
	it( 'calls tokenize on click', async () => {
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		expect( venmo.tokenize ).toHaveBeenCalled();
	} );

	it( 'does not call tokenize when donorDetails are invalid', async () => {
		const wrapper = await mountVenmo( { donorDetails: { validate: () => false } } );
		await wrapper.find( 'button' ).trigger( 'click' );
		expect( venmo.tokenize ).not.toHaveBeenCalled();
	} );

	it( 'emits submit with the correct payment data', async () => {
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ {
			payment_token: 'fake-venmo-nonce',
			device_data: 'device-data',
			user_name: 'venmouser',
			gateway_session_id: 'ctx-123',
			first_name: 'Ven',
			last_name: 'Mo',
			email: 'donor@example.org',
			phone: '555-1234',
			street_address: '123 Main St',
			customer_id: 'cust-456'
		} ] ] );
	} );

	it( 'prefers payerInfo fields over top-level detail fields', async () => {
		venmo.tokenize.mockResolvedValue( {
			nonce: 'fake-venmo-nonce',
			details: {
				username: 'venmouser',
				firstName: 'Detail',
				lastName: 'Level',
				email: 'detail@example.org',
				payerInfo: {
					firstName: 'Payer',
					lastName: 'Info',
					email: 'payer@example.org'
				}
			}
		} );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'submit' )[ 0 ][ 0 ] ).toMatchObject( {
			first_name: 'Payer',
			last_name: 'Info',
			email: 'payer@example.org'
		} );
	} );

	it( 'falls back to top-level detail fields when payerInfo is absent', async () => {
		venmo.tokenize.mockResolvedValue( {
			nonce: 'fake-venmo-nonce',
			details: {
				firstName: 'Detail',
				lastName: 'Level',
				email: 'detail@example.org'
			}
		} );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'submit' )[ 0 ][ 0 ] ).toMatchObject( {
			first_name: 'Detail',
			last_name: 'Level',
			email: 'detail@example.org'
		} );
	} );
} );

describe( 'ComboWiki Braintree Venmo cancellation handling', () => {
	const CANCEL_CODES = [
		'VENMO_CUSTOMER_CANCELED',
		'VENMO_DESKTOP_CANCELED',
		'VENMO_MOBILE_POLLING_TOKENIZATION_CANCELED'
	];

	CANCEL_CODES.forEach( ( code ) => {
		it( `does not emit an error for cancel code ${ code }`, async () => {
			const cancelError = { code };
			venmo.tokenize.mockRejectedValue( cancelError );
			const wrapper = await mountVenmo();
			await wrapper.find( 'button' ).trigger( 'click' );
			await VueTestUtils.flushPromises();
			expect( wrapper.emitted( 'error' ) ).toBeUndefined();
			expect( wrapper.emitted( 'submit' ) ).toBeUndefined();
		} );
	} );

	it( 'emits an error for non-cancel tokenize failures', async () => {
		const error = { code: 'VENMO_TOKENIZATION_FAILED', message: 'failed' };
		venmo.tokenize.mockRejectedValue( error );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'error' ) ).toEqual( [ [ error ] ] );
	} );
} );

describe( 'ComboWiki Braintree Venmo wallet fallback', () => {
	beforeEach( () => {
		// The component passes $( '#braintree-venmo' ) to showWalletFallbackFields,
		// so jQuery must be defined or the argument evaluation throws before the call.
		global.$ = jest.fn().mockReturnValue( {} );
	} );

	it( 'shows fallback fields and does not emit submit when the name is invalid', async () => {
		walletFallback.hasValidWalletName.mockReturnValue( false );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( walletFallback.showWalletFallbackFields ).toHaveBeenCalled();
		expect( wrapper.emitted( 'submit' ) ).toBeUndefined();
	} );

	it( 'shows fallback fields and does not emit submit when the email is invalid', async () => {
		walletFallback.hasValidWalletEmail.mockReturnValue( false );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( walletFallback.showWalletFallbackFields ).toHaveBeenCalled();
		expect( wrapper.emitted( 'submit' ) ).toBeUndefined();
	} );

	it( 'emits submit with corrected data after the donor fills in the fallback fields', async () => {
		walletFallback.hasValidWalletName.mockReturnValueOnce( false ).mockReturnValue( true );
		walletFallback.showWalletFallbackFields.mockImplementation( ( $container, email, onSubmit ) => {
			onSubmit( 'Fixed', 'Name', 'fixed@example.org' );
		} );
		const wrapper = await mountVenmo();
		await wrapper.find( 'button' ).trigger( 'click' );
		await VueTestUtils.flushPromises();
		expect( wrapper.emitted( 'submit' ) ).toEqual( [ [ expect.objectContaining( {
			payment_token: 'fake-venmo-nonce',
			first_name: 'Fixed',
			last_name: 'Name',
			email: 'fixed@example.org'
		} ) ] ] );
	} );
} );
