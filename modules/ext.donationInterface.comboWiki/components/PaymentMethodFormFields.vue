<template>
	<!-- The donor details a payment method form asks for, as listed in its formFields.
		Each payment method form adds its own submit button (and e.g. card number fields). -->
	<div class="payment-method-form combo-wiki__donor-details">
		<h2>{{ $i18n( 'combowiki-your-details-heading' ).text() }}</h2>
		<div class="combo-wiki__form-fields">
			<div class="combo-wiki__card-fields-row">
				<cdx-field
					v-if="showField( 'first_name' )"
					:optional="!isFieldRequired( 'first_name' )"
					:status="validation.status.first_name"
					:messages="validation.messages.first_name"
				>
					<cdx-text-input
						id="first_name"
						ref="first_name"
						v-model="fields.first_name"
						autocomplete="billing given-name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
						@blur="onFieldBlur( 'first_name' )"
						@input="validation.onInput( 'first_name' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-first_name' ).text() }}
					</template>
				</cdx-field>

				<cdx-field
					v-if="showField( 'last_name' )"
					:optional="!isFieldRequired( 'last_name' )"
					:status="validation.status.last_name"
					:messages="validation.messages.last_name"
				>
					<cdx-text-input
						id="last_name"
						ref="last_name"
						v-model="fields.last_name"
						autocomplete="billing family-name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
						@blur="onFieldBlur( 'last_name' )"
						@input="validation.onInput( 'last_name' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-last_name' ).text() }}
					</template>
				</cdx-field>
			</div>

			<cdx-field
				v-if="showField( 'email' )"
				:optional="!isFieldRequired( 'email' )"
				:status="validation.status.email"
				:messages="validation.messages.email"
			>
				<cdx-text-input
					id="email"
					ref="email"
					v-model="fields.email"
					input-type="email"
					autocomplete="email"
					data-autoscroll
					:placeholder="$i18n( 'donate_interface-donor-email' ).text()"
					@blur="onFieldBlur( 'email' )"
					@input="validation.onInput( 'email' )"
				>
				</cdx-text-input>
				<template #label>
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</template>
			</cdx-field>

			<div class="combo-wiki__card-fields-row">
				<cdx-field
					v-if="showField( 'street_address' )"
					:optional="!isFieldRequired( 'street_address' )"
					:status="validation.status.street_address"
					:messages="validation.messages.street_address"
				>
					<cdx-text-input
						id="street_address"
						ref="street_address"
						v-model="fields.street_address"
						autocomplete="billing address-line1"
						autocapitalize="words"
						:placeholder="$i18n( 'donate_interface-donor-street_address' ).text()"
						@blur="onFieldBlur( 'street_address' )"
						@input="validation.onInput( 'street_address' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-street_address' ).text() }}
					</template>
				</cdx-field>
				<cdx-field
					v-if="showField( 'street_number' )"
					:optional="!isFieldRequired( 'street_number' )"
					:status="validation.status.street_number"
					:messages="validation.messages.street_number"
				>
					<cdx-text-input
						id="street_number"
						ref="street_number"
						v-model="fields.street_number"
						autocomplete="billing address-line1"
						:placeholder="$i18n( 'donate_interface-donor-street-number' ).text()"
						@blur="onFieldBlur( 'street_number' )"
						@input="validation.onInput( 'street_number' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-street-number' ).text() }}
					</template>
				</cdx-field>
			</div>

			<!-- Keep these 3 fields in one single row, despite of the visibility -->
			<div class="combo-wiki__card-fields-row">
				<cdx-field
					v-if="showField( 'city' )"
					:optional="!isFieldRequired( 'city' )"
					:status="validation.status.city"
					:messages="validation.messages.city"
				>
					<cdx-text-input
						id="city"
						ref="city"
						v-model="fields.city"
						autocomplete="billing address-level2"
						autocapitalize="words"
						:placeholder="$i18n( 'donate_interface-donor-city' ).text()"
						@blur="onFieldBlur( 'city' )"
						@input="validation.onInput( 'city' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-city' ).text() }}
					</template>
				</cdx-field>

				<cdx-field
					v-if="showField( 'state_province' )"
					:optional="!isFieldRequired( 'state_province' )"
					:status="validation.status.state_province"
					:messages="validation.messages.state_province"
				>
					<!-- Countries with a subdivision list (e.g. AU, CA) pick a code, as on the Mustache forms.
						A native select styled by Codex, unlike cdx-select, so browsers can autofill it.
						cdx-field only links its label to Codex inputs, so name it with aria-label -->
					<select
						v-if="stateProvinceOptions.length"
						id="state_province"
						ref="state_province"
						v-model="stateProvince"
						class="cdx-select combo-wiki__select"
						:class="{
							'combo-wiki__select--error': validation.status.state_province === 'error',
							'combo-wiki__select--placeholder': !stateProvince
						}"
						autocomplete="billing address-level1"
						:aria-label="stateProvinceLabel"
						@change="onFieldSelect( 'state_province' )"
					>
						<option value="" disabled>
							{{ stateProvinceLabel }}
						</option>
						<option
							v-for="option in stateProvinceOptions"
							:key="option.value"
							:value="option.value"
						>
							{{ option.label }}
						</option>
					</select>
					<!-- Fallback option to let user insert manually their province abbreviation code -->
					<cdx-text-input
						v-else
						id="state_province"
						ref="state_province"
						v-model="fields.state_province"
						autocomplete="billing address-level1"
						:placeholder="stateProvinceLabel"
						@blur="onFieldBlur( 'state_province' )"
						@input="validation.onInput( 'state_province' )"
					>
					</cdx-text-input>
					<template #label>
						{{ stateProvinceLabel }}
					</template>
				</cdx-field>

				<cdx-field
					v-if="showField( 'postal_code' )"
					:optional="!isFieldRequired( 'postal_code' )"
					:status="validation.status.postal_code"
					:messages="validation.messages.postal_code"
				>
					<cdx-text-input
						id="postal_code"
						ref="postal_code"
						v-model="fields.postal_code"
						autocomplete="billing postal-code"
						autocapitalize="characters"
						:placeholder="$i18n( 'donate_interface-donor-postal_code' ).text()"
						@blur="onFieldBlur( 'postal_code' )"
						@input="validation.onInput( 'postal_code' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-postal_code' ).text() }}
					</template>
				</cdx-field>
			</div>

			<cdx-field
				v-if="showField( 'fiscal_number' )"
				:optional="!isFieldRequired( 'fiscal_number' )"
				:status="validation.status.fiscal_number"
				:messages="validation.messages.fiscal_number"
			>
				<cdx-text-input
					id="fiscal_number"
					ref="fiscal_number"
					v-model="fields.fiscal_number"
					autocomplete="on"
					:placeholder="fiscalNumberLabel"
					@blur="onFieldBlur( 'fiscal_number' )"
					@input="validation.onInput( 'fiscal_number' )"
				>
				</cdx-text-input>
				<template #label>
					{{ fiscalNumberLabel }}
				</template>
			</cdx-field>
		</div>
	</div>
</template>

<script>
const { defineComponent, reactive, watch } = require( 'vue' );
const { CdxField, CdxTextInput } = require( '@wikimedia/codex' );
const { useFieldValidation } = require( '../composables/useFieldValidation.js' );
const { donateParamMap } = require( '../api.js' );

const EMAIL_PATTERN = '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$';

module.exports = exports = defineComponent( {
	name: 'PaymentMethodFormFields',

	components: {
		'cdx-text-input': CdxTextInput,
		'cdx-field': CdxField
	},

	inject: [ 'params' ],

	props: {
		donation: {
			type: Object,
			required: true
		},
		// The fields to show, from DonationInterfaceFormFields.method: true (required) or 'optional'
		formFields: {
			type: Object,
			default: () => ( {} )
		},
		validationRules: {
			type: Object,
			default: () => ( {} )
		}
	},

	// update:valid lets the parent form show an error summary that clears once the details are fixed
	// update:details keeps the donation up to date as the donor leaves each field, see PaymentForm
	emits: [ 'update:valid', 'update:details' ],
	// Called through a template ref by the payment method form when the donor submits
	expose: [ 'validate', 'getValues' ],

	setup( props, ctx ) {
		// Set only the values of fields this payment method should show, keyed by the server field names
		// used in formFields and in the donate API.
		const fields = reactive( {} );
		for ( const [ fieldName, fieldValue ] of Object.entries( donateParamMap ) ) {
			if ( [ true, 'optional' ].includes( props.formFields[ fieldName ] ) ) {
				fields[ fieldName ] = props.donation[ fieldValue ];
			}
		}

		const rules = Object.assign( {}, props.validationRules );
		if ( rules.email ) {
			rules.email = rules.email.concat( {
				pattern: EMAIL_PATTERN,
				message: mw.msg( 'donate_interface-error-msg-invalid-email' )
			} );
		}
		const validation = useFieldValidation( rules, fields );
		watch( validation.isValid, ( valid ) => ctx.emit( 'update:valid', valid ), { immediate: true } );

		return { fields, validation };
	},

	computed: {
		stateProvinceOptions() {
			// [ { value: 'ON', label: 'Ontario' }, ... ] from Special:Donate, empty if none for the country
			return this.params.DonationInterfaceStateProvinceOptions || [];
		},
		stateProvince: {
			// The placeholder option has the empty value, so it shows until a state is picked
			get() {
				return this.fields.state_province || '';
			},
			set( value ) {
				this.fields.state_province = value;
			}
		},
		stateProvinceLabel() {
			// Canada and Australia name their subdivisions differently
			const country = ( this.donation.country || '' ).toLowerCase();
			const suffix = [ 'ca', 'au' ].includes( country ) ? '-' + country : '';
			// Messages that can be used here:
			// * donate_interface-donor-state_province
			// * donate_interface-donor-state_province-au
			// * donate_interface-donor-state_province-ca
			return this.$i18n( 'donate_interface-donor-state_province' + suffix ).text();
		},
		fiscalNumberLabel() {
			const country = ( this.donation.country || '' ).toLowerCase();
			const suffix = [ 'ar', 'bo', 'br', 'cl', 'co', 'in', 'mx', 'pe', 'uy', 'za' ].includes( country ) ? '-' + country : '';
			// Messages that can be used here:
			// * donate_interface-donor-fiscal_number
			// * donate_interface-donor-fiscal_number-ar
			// * donate_interface-donor-fiscal_number-bo
			// * donate_interface-donor-fiscal_number-br
			// * donate_interface-donor-fiscal_number-cl
			// * donate_interface-donor-fiscal_number-co
			// * donate_interface-donor-fiscal_number-in
			// * donate_interface-donor-fiscal_number-mx
			// * donate_interface-donor-fiscal_number-pe
			// * donate_interface-donor-fiscal_number-uy
			// * donate_interface-donor-fiscal_number-za
			return this.$i18n( 'donate_interface-donor-fiscal_number' + suffix ).text();
		}
	},

	methods: {
		showField( name ) {
			return [ true, 'optional' ].includes( this.formFields[ name ] );
		},
		isFieldRequired( name ) {
			return this.formFields[ name ] === true;
		},
		/**
		 * Checks the field's format and reports the details, so the donation is updated
		 * once the donor leaves a field rather than on every keystroke.
		 *
		 * @param {string} name
		 */
		onFieldBlur( name ) {
			this.validation.onBlur( name );
			this.$emit( 'update:details', this.getValues() );
		},
		/**
		 * Clears the field's error and reports the details: a pick from a list is complete
		 * at once, unlike typing.
		 *
		 * @param {string} name
		 */
		onFieldSelect( name ) {
			this.validation.onInput( name );
			this.$emit( 'update:details', this.getValues() );
		},
		/**
		 * Shows the errors for every field and focuses the first invalid one.
		 * Called by the parent form when the donor submits.
		 *
		 * @return {boolean} Whether all the fields are valid
		 */
		validate() {
			const invalidFields = this.validation.validateAll();
			// Not stored in a variable: no-jquery/variable-pattern treats anything from $refs as jQuery
			if ( invalidFields.length && this.$refs[ invalidFields[ 0 ] ] ) {
				this.$refs[ invalidFields[ 0 ] ].focus();
			}
			return !invalidFields.length;
		},
		/**
		 * @return {Object} The shown fields' values, keyed by server field name
		 */
		getValues() {
			return Object.assign( {}, this.fields );
		}
	}
} );
</script>
