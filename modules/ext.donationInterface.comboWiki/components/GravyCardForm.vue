<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div>
			<h2>{{ $i18n( 'combowiki-your-details-heading' ).text() }}</h2>

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
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
						@blur="validation.onBlur( 'first_name' )"
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
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
						@blur="validation.onBlur( 'last_name' )"
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
					data-autoscroll
					:placeholder="$i18n( 'donate_interface-donor-email' ).text()"
					@blur="validation.onBlur( 'email' )"
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
						:placeholder="$i18n( 'donate_interface-donor-street_address' ).text()"
						@blur="validation.onBlur( 'street_address' )"
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
						:placeholder="$i18n( 'donate_interface-donor-street-number' ).text()"
						@blur="validation.onBlur( 'street_number' )"
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
						:placeholder="$i18n( 'donate_interface-donor-city' ).text()"
						@blur="validation.onBlur( 'city' )"
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
					<!-- Countries with a subdivision list (e.g. AU, CA) pick a code, as on the Mustache forms -->
					<cdx-select
						v-if="stateProvinceOptions.length"
						v-model:selected="fields.state_province"
						:menu-items="stateProvinceOptions"
						:default-label="stateProvinceLabel"
						@update:selected="validation.onInput( 'state_province' )"
					></cdx-select>
					<!-- Fallback option to let user insert manually their province abbreviation code -->
					<cdx-text-input
						v-else
						id="state_province"
						ref="state_province"
						v-model="fields.state_province"
						:placeholder="stateProvinceLabel"
						@blur="validation.onBlur( 'state_province' )"
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
						:placeholder="$i18n( 'donate_interface-donor-postal_code' ).text()"
						@blur="validation.onBlur( 'postal_code' )"
						@input="validation.onInput( 'postal_code' )"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-postal_code' ).text() }}
					</template>
				</cdx-field>
			</div>
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
				:placeholder="fiscalNumberLabel"
				@blur="validation.onBlur( 'fiscal_number' )"
				@input="validation.onInput( 'fiscal_number' )"
			>
			</cdx-text-input>
			<template #label>
				{{ fiscalNumberLabel }}
			</template>
		</cdx-field>

		<div id="secure-fields-section">
			<div>
				<cdx-label class="secure-field-label">
					{{ $i18n( 'donate_interface-donor-card-num' ).text() }}
				</cdx-label>
				<input id="combo-cc-number" data-autoscroll>
			</div>

			<div class="combo-wiki__card-fields-row">
				<div>
					<cdx-label class="secure-field-label">
						{{ $i18n( 'donate_interface-donor-expiration' ).text() }}
					</cdx-label>
					<input id="combo-cc-expiry" data-autoscroll>
				</div>
				<div>
					<cdx-label class="secure-field-label">
						{{ $i18n( 'donate_interface-donor-security' ).text() }}
					</cdx-label>
					<input id="combo-cc-cvv" data-autoscroll>
				</div>
			</div>
		</div>

		<cdx-message
			v-if="showErrorSummary"
			type="error"
			class="combo-wiki__error-summary"
		>
			{{ $i18n( 'donate_interface-error-msg-validation' ).text() }}
		</cdx-message>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global SecureFields */
const { defineComponent, reactive } = require( 'vue' );
const { CdxButton, CdxField, CdxLabel, CdxMessage, CdxTextInput, CdxSelect } = require( '@wikimedia/codex' );
const { loadScript } = require( '../utils.js' );
const { useFieldValidation } = require( '../composables/useFieldValidation.js' );

const EMAIL_PATTERN = '^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$';

module.exports = exports = defineComponent( {
	name: 'GravyCardForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput,
		'cdx-field': CdxField,
		'cdx-select': CdxSelect,
		'cdx-label': CdxLabel,
		'cdx-message': CdxMessage
	},

	inject: [ 'params' ],

	props: {
		donation: {
			type: Object,
			required: true
		},
		formFields: {
			type: Object,
			default: () => ( {} )
		},
		validationRules: {
			type: Object,
			default: () => ( {} )
		}
	},

	emits: [ 'presubmit', 'submit', 'error' ],

	setup( props ) {
		// Donor details keyed by the server field names used in formFields and the donate API
		const fields = reactive( {
			first_name: props.donation.firstName,
			last_name: props.donation.lastName,
			email: props.donation.email,
			street_address: props.donation.streetAddress,
			city: props.donation.city,
			state_province: props.donation.stateProvince,
			postal_code: props.donation.postalCode,
			street_number: props.donation.streetNumber,
			fiscal_number: props.donation.fiscalNumber
		} );

		const rules = Object.assign( {}, props.validationRules );
		if ( rules.email ) {
			rules.email = rules.email.concat( {
				pattern: EMAIL_PATTERN,
				message: mw.msg( 'donate_interface-error-msg-invalid-email' )
			} );
		}
		const validation = useFieldValidation( rules, fields );

		return { fields, validation, donorFieldsValid: validation.isValid };
	},

	data() {
		return {
			secureFields: null,
			fieldsReady: false,
			formValid: false,
			submitAttempted: false
		};
	},

	computed: {
		stateProvinceOptions() {
			// [ { value: 'ON', label: 'Ontario' }, ... ] from Special:Donate, empty if none for the country
			return this.params.DonationInterfaceStateProvinceOptions || [];
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
		},
		showErrorSummary() {
			return this.submitAttempted && !( this.donorFieldsValid && this.formValid );
		}
	},

	methods: {
		showField( name ) {
			return [ true, 'optional' ].includes( this.formFields[ name ] );
		},
		isFieldRequired( name ) {
			return this.formFields[ name ] === true;
		},
		setupSecureFields( config, sessionId ) {
			this.secureFields = new SecureFields( {
				gr4vyId: config.gravyID,
				environment: config.environment,
				sessionId: sessionId
			} );

			this.secureFields.addCardNumberField( '#combo-cc-number' );
			this.secureFields.addSecurityCodeField( '#combo-cc-cvv' );
			this.secureFields.addExpiryDateField( '#combo-cc-expiry' );
			this.fieldsReady = true;

			this.secureFields.addEventListener( SecureFields.Events.FORM_CHANGE, ( data ) => {
				if ( data ) {
					this.formValid = data.complete;
				}
			} );

			this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_SUCCESS, ( data ) => {
				this.$emit( 'submit', Object.assign( {}, this.fields, {
					gateway_session_id: sessionId,
					card_scheme: data.scheme,
					color_depth: screen.colorDepth || 24,
					screen_height: screen.height || 0,
					screen_width: screen.width || 0,
					time_zone_offset: Math.floor( new Date().getTimezoneOffset() ) || 0
				} ) );
			} );

			this.secureFields.addEventListener( SecureFields.Events.CARD_VAULT_FAILURE, ( data ) => {
				console.log( 'card vault failure', data );
				this.$emit( 'error', 'card-vault-failure' );
			} );
		},
		submit() {
			this.submitAttempted = true;
			const invalidFields = this.validation.validateAll();
			if ( invalidFields.length ) {
				// Only the text inputs have refs, so the state_province select isn't focused.
				// Not stored in a variable: no-jquery/variable-pattern treats anything from $refs as jQuery
				if ( this.$refs[ invalidFields[ 0 ] ] ) {
					this.$refs[ invalidFields[ 0 ] ].focus();
				}
				return;
			}
			// SecureFields only says whether all card fields are complete, so the summary covers them
			if ( !this.formValid ) {
				return;
			}
			this.secureFields.submit();
		}

	},

	mounted() {
		const config = this.params.gravyConfiguration;
		const onSuccessfulSessionCreated = ( sessionId ) => {
			this.setupSecureFields( config, sessionId );
		};
		const onError = () => {
			this.$emit( 'error', 'card-session-setup-failed' );
		};
		loadScript( config.secureFieldsJsScript )
			.then( () => {
				if ( this.donation.gateway_session_id ) {
					onSuccessfulSessionCreated( this.donation.gateway_session_id );
					return;
				}
				this.$emit( 'presubmit', this.donation, onSuccessfulSessionCreated, onError );
			} )
			.catch( () => {
				onError();
			} );

	},

	beforeUnmount() {
		// Drop the SDK instance so it doesn't stay in browser memory when not needed
		this.secureFields = null;
	}
} );

</script>
