/* global describe it expect */

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
