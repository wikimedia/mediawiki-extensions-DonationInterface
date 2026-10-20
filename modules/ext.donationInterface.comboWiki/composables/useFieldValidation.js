const { reactive, computed } = require( 'vue' );

/**
 * Client-side validation for a ComboWiki form, following the Codex form guidance:
 * formats are checked when a field loses focus, required fields when the donor
 * submits, and a field's error clears as soon as the donor edits it.
 *
 * Only fields in `fields` are validated, so a rule for a field the form doesn't
 * draw (e.g. country, which is chosen outside the payment method forms) is ignored.
 *
 * Each form calls this for its own state, unlike useAppState which is shared.
 *
 * @param {Object} rules Rules keyed by field name, from DonateValidationRules
 *  (e.g. DonateValidationRules.method.cc). Each rule has a message, and either
 *  required: true or a pattern.
 * @param {Object} fields The form's reactive field values, keyed by field name
 * @return {Object} status and messages for each field's cdx-field, plus onBlur,
 *  onInput, validateAll and isValid
 */
function useFieldValidation( rules, fields ) {
	const fieldNames = Object.keys( fields ).filter( ( name ) => rules[ name ] );
	const status = reactive( {} );
	const messages = reactive( {} );
	fieldNames.forEach( ( name ) => {
		status[ name ] = 'default';
		messages[ name ] = {};
	} );

	const isEmpty = ( name ) => !String( fields[ name ] || '' ).trim();

	/**
	 * @param {string} name
	 * @param {boolean} checkRequired Whether an empty required field counts as failing
	 * @return {Object|undefined} The first rule the field fails
	 */
	const findFailedRule = ( name, checkRequired ) => rules[ name ].find( ( rule ) => {
		if ( rule.required ) {
			return checkRequired && isEmpty( name );
		}
		if ( rule.pattern ) {
			return !isEmpty( name ) && !new RegExp( rule.pattern ).test( String( fields[ name ] ) );
		}
		return false;
	} );

	const showResult = ( name, failedRule ) => {
		status[ name ] = failedRule ? 'error' : 'default';
		messages[ name ] = failedRule ? { error: failedRule.message } : {};
	};

	return {
		status,
		messages,
		/**
		 * Checks the field's format when the field has a value. An empty field keeps
		 * its current state, so a required error shown on submit stays until the
		 * donor types in the field.
		 *
		 * @param {string} name
		 */
		onBlur( name ) {
			if ( fieldNames.includes( name ) && !isEmpty( name ) ) {
				showResult( name, findFailedRule( name, false ) );
			}
		},
		/**
		 * Clears the field's error as soon as the donor edits it.
		 *
		 * @param {string} name
		 */
		onInput( name ) {
			if ( fieldNames.includes( name ) ) {
				showResult( name, null );
			}
		},
		/**
		 * Checks every field, including empty required ones, and shows the errors.
		 *
		 * @return {string[]} The invalid field names, in the form's field order
		 */
		validateAll() {
			return fieldNames.filter( ( name ) => {
				const failedRule = findFailedRule( name, true );
				showResult( name, failedRule );
				return Boolean( failedRule );
			} );
		},
		isValid: computed( () => fieldNames.every( ( name ) => !findFailedRule( name, true ) ) )
	};
}

module.exports = {
	useFieldValidation
};
