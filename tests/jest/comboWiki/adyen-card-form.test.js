/* global describe it expect jest beforeEach afterEach */

const VueTestUtils = require( '@vue/test-utils' );

// The checkout is shared by every Adyen component on the page and built from
// mw.config by utils.js, so the form is given one to use
jest.mock( '../../../modules/ext.donationInterface.comboWiki/utils.js', () => Object.assign(
	{},
	jest.requireActual( '../../../modules/ext.donationInterface.comboWiki/utils.js' ),
	{ getAdyenCheckout: jest.fn() }
) );
const { getAdyenCheckout } = require( '../../../modules/ext.donationInterface.comboWiki/utils.js' );

const AdyenCardForm = require( '../../../modules/ext.donationInterface.comboWiki/components/AdyenCardForm.vue' );

// A one-time card donation as PaymentMethodForm passes it down
const DONATION = {
	amount: 10,
	currency: 'USD',
	country: 'US',
	gateway: 'adyen',
	paymentMethod: 'adyen_card'
};

// Params as init.js provides them. The form only checks that Adyen is configured;
// getAdyenCheckout reads the configuration itself.
const PARAMS = {
	wmf_token: 'TOKEN',
	DonationInterfaceOtherWaysURL: 'https://donate.wikimedia.org/ways',
	adyenConfiguration: {
		clientKey: 'test_CLIENT_KEY',
		environment: 'test',
		locale: 'en-US',
		paymentMethodsResponse: { paymentMethods: [] },
		script: { src: 'https://checkoutshopper-test.adyen.com/checkout.js' }
	}
};

// Adyen's card component, the checkout getAdyenCheckout resolves with, and the
// redirect action that posts formData to the card issuer's 3D Secure page
let card;
let checkout;
let action;

beforeEach( () => {
	card = { mount: jest.fn(), unmount: jest.fn(), submit: jest.fn() };
	action = { mount: jest.fn() };
	checkout = { createFromAction: jest.fn( () => action ) };
	window.AdyenWeb = { Card: jest.fn( () => card ) };
	// Undo any test's mockReturnValue: clearMocks only clears the calls
	getAdyenCheckout.mockReset();
	getAdyenCheckout.mockResolvedValue( checkout );
} );

afterEach( () => {
	delete window.AdyenWeb;
} );

/**
 * Mounts the form and waits for the Adyen setup in mounted() to finish.
 *
 * @return {Promise<Object>}
 */
const mountForm = async () => {
	const wrapper = VueTestUtils.mount( AdyenCardForm, {
		props: { donation: DONATION },
		global: {
			provide: { params: PARAMS }
		}
	} );
	await VueTestUtils.flushPromises();
	return wrapper;
};

/**
 * Submits valid card details, as Adyen does on card.submit()
 *
 * @param {Object} wrapper
 * @return {Function} The success callback the form emitted with its submit event
 */
const submitCard = ( wrapper ) => {
	const adyenConfig = window.AdyenWeb.Card.mock.calls[ 0 ][ 1 ];
	adyenConfig.onSubmit( {
		isValid: true,
		data: { paymentMethod: { type: 'scheme', encryptedCardNumber: 'ENCRYPTED', brand: 'visa' } }
	}, { state: {} } );
	return wrapper.emitted( 'submit' )[ 0 ][ 1 ];
};

describe( 'ComboWiki Adyen card form after the donation is submitted', () => {
	it( 'emits a success callback for PaymentMethodForm to call with the API result', async () => {
		const wrapper = await mountForm();
		expect( submitCard( wrapper ) ).toBe( wrapper.vm.handleSubmitSuccess );
	} );

	it( 'posts the form data to the 3D Secure page when the API sends some', async () => {
		const wrapper = await mountForm();
		submitCard( wrapper )( { result: {
			isFailed: false,
			redirect: 'https://bank.example/3ds',
			formData: { PaReq: 'PAREQ', MD: 'MD', TermUrl: 'https://donate.example/return' }
		} } );

		expect( checkout.createFromAction ).toHaveBeenCalledWith( {
			paymentMethodType: 'scheme',
			url: 'https://bank.example/3ds',
			data: { PaReq: 'PAREQ', MD: 'MD', TermUrl: 'https://donate.example/return' },
			method: 'POST',
			type: 'redirect'
		} );
		expect( action.mount ).toHaveBeenCalledWith( '#combo-adyen-card-action-container' );
	} );

	it.each( [
		[ 'no form data', { redirect: 'https://donate.example/thank-you' } ],
		// PHP's empty array serialises as a JSON array
		[ 'form data as an empty array', { redirect: 'https://donate.example/thank-you', formData: [] } ],
		[ 'empty form data', { redirect: 'https://donate.example/thank-you', formData: {} } ],
		[ 'null form data', { formData: null } ]
	] )( 'leaves the redirect to the payment form when the API sends %s', async ( label, response ) => {
		const wrapper = await mountForm();
		submitCard( wrapper )( { result: Object.assign( { isFailed: false }, response ) } );
		expect( checkout.createFromAction ).not.toHaveBeenCalled();
	} );

	it( 'does nothing when the API sends no result', async () => {
		const wrapper = await mountForm();
		expect( () => wrapper.vm.handleSubmitSuccess( {} ) ).not.toThrow();
		expect( checkout.createFromAction ).not.toHaveBeenCalled();
	} );

	it( 'shows the general error when there is no Adyen checkout to post the form data with', async () => {
		// Adyen is still loading, so the form has no checkout
		getAdyenCheckout.mockReturnValue( new Promise( () => {} ) );
		const wrapper = await mountForm();
		wrapper.vm.handleSubmitSuccess( { result: { redirect: 'https://bank.example/3ds', formData: { MD: 'MD' } } } );

		expect( checkout.createFromAction ).not.toHaveBeenCalled();
		expect( wrapper.emitted( 'error' ) ).toEqual( [ [ {
			type: 'validation',
			messages: [ 'donate_interface-error-msg-general:[https://donate.wikimedia.org/ways]' ]
		} ] ] );
	} );
} );
