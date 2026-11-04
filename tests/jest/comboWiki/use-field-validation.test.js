/* global describe it expect jest */

const { reactive } = require( 'vue' );
const { useFieldValidation } = require( '../../../modules/ext.donationInterface.comboWiki/composables/useFieldValidation.js' );

// DonateValidationRules.method.cc as Special:Donate sends them for ?country=BR
// (messageKey left out, the composable only uses message)
const BR_CARD_RULES = {
	country: [
		{ required: true, message: 'Please enter your country' }
	],
	first_name: [
		{ required: true, message: 'Please enter your first name' }
	],
	fiscal_number: [
		{ required: true, message: 'Please enter your CPF/CNPJ' },
		{ pattern: '^[^0-9]*([0-9][^0-9]*){11,14}$', message: 'Please correct the errors in your CPF/CNPJ' }
	]
};

/**
 * @param {Object} [values] Starting values for the form's fields
 * @return {{fields: Object, validation: Object}}
 */
function setup( values ) {
	// The fields the card form draws. country is chosen outside the form, so it isn't one.
	const fields = reactive( Object.assign( { first_name: '', fiscal_number: '' }, values ) );
	return { fields, validation: useFieldValidation( BR_CARD_RULES, fields ) };
}

describe( 'useFieldValidation', () => {
	it( 'shows the required message for each empty required field on submit', () => {
		const { validation } = setup();

		// country has a rule but isn't one of the form's fields, so it's left out
		expect( validation.validateAll() ).toEqual( [ 'first_name', 'fiscal_number' ] );
		expect( validation.status.first_name ).toBe( 'error' );
		expect( validation.messages.first_name ).toEqual( { error: 'Please enter your first name' } );
		expect( validation.messages.fiscal_number ).toEqual( { error: 'Please enter your CPF/CNPJ' } );
	} );

	it( 'checks the format on blur, but leaves an empty field for submit', () => {
		const { fields, validation } = setup();

		validation.onBlur( 'fiscal_number' );
		expect( validation.status.fiscal_number ).toBe( 'default' );

		fields.fiscal_number = '12345';
		validation.onBlur( 'fiscal_number' );
		expect( validation.status.fiscal_number ).toBe( 'error' );
		expect( validation.messages.fiscal_number ).toEqual( { error: 'Please correct the errors in your CPF/CNPJ' } );
	} );

	it( 'clears a field\'s error when the donor edits it', () => {
		const { validation } = setup();
		validation.validateAll();

		validation.onInput( 'first_name' );

		expect( validation.status.first_name ).toBe( 'default' );
		expect( validation.status.fiscal_number ).toBe( 'error' );
	} );

	it( 'is valid when every field the form draws is valid', () => {
		const { validation } = setup( { first_name: 'Nome', fiscal_number: '123.456.789-09' } );

		expect( validation.validateAll() ).toEqual( [] );
		expect( validation.isValid.value ).toBe( true );
	} );

	it( 'is valid when the form has no fields', () => {
		const validation = useFieldValidation( BR_CARD_RULES, reactive( {} ) );

		expect( validation.validateAll() ).toEqual( [] );
		expect( validation.isValid.value ).toBe( true );
	} );
} );

describe( 'useFieldValidation callback and warning rules', () => {
	// As PaymentMethodFormFields adds them for email: the format is an error, a likely typo a warning
	const looksLikeTypo = jest.fn( ( value ) => value.endsWith( '@gmial.com' ) );
	const EMAIL_RULES = {
		email: [
			{ required: true, message: 'Please enter your email' },
			{ pattern: '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$', message: 'Please enter a valid email' },
			{ callback: looksLikeTypo, message: 'Did you mean gmail.com?', warning: true }
		]
	};

	const setupEmail = ( email ) => {
		const fields = reactive( { email } );
		return { fields, validation: useFieldValidation( EMAIL_RULES, fields ) };
	};

	it( 'shows a warning, keyed by status, when a warning rule fails on blur', () => {
		const { validation } = setupEmail( 'jo@gmial.com' );

		validation.onBlur( 'email' );

		expect( looksLikeTypo ).toHaveBeenCalledWith( 'jo@gmial.com' );
		expect( validation.status.email ).toBe( 'warning' );
		expect( validation.messages.email ).toEqual( { warning: 'Did you mean gmail.com?' } );
	} );

	it( 'is still valid while a field only has a warning', () => {
		const { validation } = setupEmail( 'jo@gmial.com' );

		// validateAll reports the field so the warning shows; status tells it from an error
		expect( validation.validateAll() ).toEqual( [ 'email' ] );
		expect( validation.status.email ).toBe( 'warning' );
		expect( validation.isValid.value ).toBe( true );
	} );

	it( 'shows an error instead of the warning when an error rule also fails', () => {
		const { validation } = setupEmail( 'not an email@gmial.com' );

		validation.onBlur( 'email' );

		expect( validation.status.email ).toBe( 'error' );
		expect( validation.messages.email ).toEqual( { error: 'Please enter a valid email' } );
		expect( validation.isValid.value ).toBe( false );
	} );

	it( 'does not run the callback for an empty field', () => {
		const { validation } = setupEmail( '' );

		expect( validation.validateAll() ).toEqual( [ 'email' ] );
		expect( validation.messages.email ).toEqual( { error: 'Please enter your email' } );
		expect( looksLikeTypo ).not.toHaveBeenCalled();
	} );

	it( 'clears the warning when the donor edits the field', () => {
		const { validation } = setupEmail( 'jo@gmial.com' );
		validation.onBlur( 'email' );

		validation.onInput( 'email' );

		expect( validation.status.email ).toBe( 'default' );
		expect( validation.messages.email ).toEqual( {} );
	} );
} );
