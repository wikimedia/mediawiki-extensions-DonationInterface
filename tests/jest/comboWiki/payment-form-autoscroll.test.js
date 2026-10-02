/* global describe it expect jest afterEach */

const VueTestUtils = require( '@vue/test-utils' );

const PaymentForm = require( '../../../modules/ext.donationInterface.comboWiki/views/PaymentForm.vue' );
const SmsOptin = require( '../../../modules/ext.donationInterface.comboWiki/components/SmsOptin.vue' );

const { scrollToFirstEmptyField } = PaymentForm.methods;

const PREFILLED_DONATION = { amount: '10', frequency: 'once', paymentMethod: 'card' };

// Params for a banner click that prefills the amount and card payment method
const PREFILLED_PARAMS = {
	amount: '10',
	country: 'US',
	currency: 'USD',
	order_id: '1336.1',
	payment_method: 'cc',
	gateway: 'gravy',
	language: 'en',
	paymentMethods: [ { method: 'cc', gateway: 'gravy' } ],
	wgDonationInterfaceAmountRules: { currency: 'USD', min: 1, max: 12000 },
	wgDonationInterfaceCurrencyRates: { USD: 1 }
};

/**
 * Builds a form to scroll through. jsdom doesn't do layout, so every element counts as
 * rendered unless it has data-test-hidden, and scrolling and focus are recorded.
 *
 * @param {string} html
 * @return {HTMLElement}
 */
function buildForm( html ) {
	const form = document.createElement( 'form' );
	form.innerHTML = html;
	Array.from( form.querySelectorAll( '*' ) ).forEach( ( el ) => {
		el.getClientRects = () => ( el.hasAttribute( 'data-test-hidden' ) ? [] : [ {} ] );
		el.scrollIntoView = jest.fn();
		el.focus = jest.fn();
	} );
	return form;
}

/**
 * Runs scrollToFirstEmptyField against the form and returns the element it scrolled to.
 *
 * @param {HTMLElement} form
 * @param {Object} [donation]
 * @return {HTMLElement|undefined}
 */
function scrollTarget( form, donation = PREFILLED_DONATION ) {
	scrollToFirstEmptyField.call( { donation, $el: form } );
	return Array.from( form.querySelectorAll( '*' ) )
		.find( ( el ) => el.scrollIntoView.mock.calls.length > 0 );
}

describe( 'ComboWiki scroll to the first field the donor needs', () => {
	it.each( [
		[ 'amount', { amount: null } ],
		[ 'frequency', { frequency: null } ],
		[ 'payment method', { paymentMethod: null } ]
	] )( 'does nothing when the %s is not prefilled', ( _, missing ) => {
		const form = buildForm( '<input id="email" data-autoscroll>' );
		expect( scrollTarget( form, Object.assign( {}, PREFILLED_DONATION, missing ) ) ).toBeUndefined();
	} );

	it( 'scrolls to and focuses the first empty marked input', () => {
		const form = buildForm( `
			<input id="first" data-autoscroll>
			<input id="last" data-autoscroll>
		` );
		const target = scrollTarget( form );
		expect( target.id ).toBe( 'first' );
		expect( target.scrollIntoView ).toHaveBeenCalledWith( expect.objectContaining( { behavior: 'smooth' } ) );
		// The scroll does the moving, so focus mustn't jump the page as well
		expect( target.focus ).toHaveBeenCalledWith( { preventScroll: true } );
	} );

	it( 'ignores fields that are not marked', () => {
		const form = buildForm( `
			<input id="other-amount">
			<input id="employer">
			<input id="email" data-autoscroll>
		` );
		expect( scrollTarget( form ).id ).toBe( 'email' );
	} );

	it( 'skips marked fields the donor already filled in', () => {
		const form = buildForm( `
			<input id="first" data-autoscroll value="Jimmy">
			<select id="state" data-autoscroll><option value="">-</option></select>
		` );
		expect( scrollTarget( form ).id ).toBe( 'state' );
	} );

	it( 'skips disabled and hidden fields', () => {
		const form = buildForm( `
			<input id="disabled" data-autoscroll disabled>
			<input id="hidden" data-autoscroll data-test-hidden>
			<textarea id="comment" data-autoscroll></textarea>
		` );
		expect( scrollTarget( form ).id ).toBe( 'comment' );
	} );

	it( 'always counts marked buttons and containers, which have no value to check', () => {
		const form = buildForm( `
			<input id="email" data-autoscroll value="donor@example.org">
			<div id="applepay-container" data-autoscroll></div>
			<button id="donate" data-autoscroll></button>
		` );
		expect( scrollTarget( form ).id ).toBe( 'applepay-container' );
	} );

	it( 'skips a disabled marked button', () => {
		const form = buildForm( `
			<button id="submit" data-autoscroll disabled></button>
			<div id="card-fields" data-autoscroll></div>
		` );
		expect( scrollTarget( form ).id ).toBe( 'card-fields' );
	} );

	it( 'does nothing when every marked field is filled in', () => {
		const form = buildForm( '<input id="email" data-autoscroll value="donor@example.org">' );
		expect( scrollTarget( form ) ).toBeUndefined();
	} );
} );

describe( 'ComboWiki autoscroll timing', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	/**
	 * @param {Object} params
	 * @return {Promise<Object>} The wrapper and the scroll spy
	 */
	async function mountForm( params ) {
		// Spy before mounting, as the methods are bound when the component is created
		const scroll = jest.spyOn( PaymentForm.methods, 'scrollToFirstEmptyField' ).mockImplementation( () => {} );
		const wrapper = VueTestUtils.shallowMount( PaymentForm, {
			global: {
				provide: { params: Object.assign( {}, PREFILLED_PARAMS, params ) },
				// variantHelper.js registers SmsOptin under this name for the smsOptin variant
				components: { 'sms-optin': SmsOptin }
			}
		} );
		await VueTestUtils.flushPromises();
		return { wrapper, scroll };
	}

	it( 'scrolls once the form has mounted', async () => {
		const { scroll } = await mountForm( {} );
		expect( scroll ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'waits for the async SMS opt in fields before scrolling', async () => {
		const { wrapper, scroll } = await mountForm( {
			variant: 'smsOptin',
			DonationInterfaceFormFields: { phone: 'optional' }
		} );
		expect( scroll ).not.toHaveBeenCalled();

		wrapper.findComponent( SmsOptin ).vm.$emit( 'ready' );
		expect( scroll ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'ComboWiki SMS opt in', () => {
	it( 'tells the form when its fields are in the page', () => {
		const wrapper = VueTestUtils.mount( SmsOptin );
		expect( wrapper.emitted( 'ready' ) ).toHaveLength( 1 );
	} );
} );
