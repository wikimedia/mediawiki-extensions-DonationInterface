<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div>
			<h2>{{ $i18n( 'combowiki-your-details-heading' ).text() }}</h2>

			<div class="combo-wiki__card-fields-row">
				<cdx-field v-if="showField( 'first_name' )" :optional="!isFieldRequired( 'first_name' )">
					<cdx-text-input
						id="first_name"
						v-model="fields.first_name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-first_name' ).text() }}
					</template>
				</cdx-field>

				<cdx-field v-if="showField( 'last_name' )" :optional="!isFieldRequired( 'last_name' )">
					<cdx-text-input
						id="last_name"
						v-model="fields.last_name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-last_name' ).text() }}
					</template>
				</cdx-field>
			</div>

			<cdx-field v-if="showField( 'email' )" :optional="!isFieldRequired( 'email' )">
				<cdx-text-input
					id="email"
					v-model="fields.email"
					data-autoscroll
					:placeholder="$i18n( 'donate_interface-donor-email' ).text()"
				>
				</cdx-text-input>
				<template #label>
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</template>
			</cdx-field>

			<div class="combo-wiki__card-fields-row">
				<cdx-field v-if="showField( 'street_address' )" :optional="!isFieldRequired( 'street_address' )">
					<cdx-text-input
						id="street_address"
						v-model="fields.street_address"
						:placeholder="$i18n( 'donate_interface-donor-street_address' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-street_address' ).text() }}
					</template>
				</cdx-field>
				<cdx-field v-if="showField( 'street_number' )" :optional="!isFieldRequired( 'street_number' )">
					<cdx-text-input
						id="street_number"
						v-model="fields.street_number"
						:placeholder="$i18n( 'donate_interface-donor-street-number' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-street-number' ).text() }}
					</template>
				</cdx-field>
			</div>

			<!-- Keep these 3 fields in one single row, despite of the visibility -->
			<div class="combo-wiki__card-fields-row">
				<cdx-field v-if="showField( 'city' )" :optional="!isFieldRequired( 'city' )">
					<cdx-text-input
						id="city"
						v-model="fields.city"
						:placeholder="$i18n( 'donate_interface-donor-city' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-city' ).text() }}
					</template>
				</cdx-field>

				<cdx-field v-if="showField( 'state_province' )" :optional="!isFieldRequired( 'state_province' )">
					<!-- Countries with a subdivision list (e.g. AU, CA) pick a code, as on the Mustache forms -->
					<cdx-select
						v-if="stateProvinceOptions.length"
						v-model:selected="fields.state_province"
						:menu-items="stateProvinceOptions"
						:default-label="stateProvinceLabel"
					></cdx-select>
					<!-- Fallback option to let user insert manually their province abbreviation code -->
					<cdx-text-input
						v-else
						id="state_province"
						v-model="fields.state_province"
						:placeholder="stateProvinceLabel"
					>
					</cdx-text-input>
					<template #label>
						{{ stateProvinceLabel }}
					</template>
				</cdx-field>

				<cdx-field v-if="showField( 'postal_code' )" :optional="!isFieldRequired( 'postal_code' )">
					<cdx-text-input
						id="postal_code"
						v-model="fields.postal_code"
						:placeholder="$i18n( 'donate_interface-donor-postal_code' ).text()"
					>
					</cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-postal_code' ).text() }}
					</template>
				</cdx-field>
			</div>
		</div>

		<cdx-field v-if="showField( 'fiscal_number' )" :optional="!isFieldRequired( 'fiscal_number' )">
			<cdx-text-input
				id="fiscal_number"
				v-model="fields.fiscal_number"
				:placeholder="fiscalNumberLabel"
			>
			</cdx-text-input>
			<template #label>
				{{ fiscalNumberLabel }}
			</template>
		</cdx-field>
		<label for="combo-cc-number">{{ $i18n( 'donate_interface-donor-card-num' ).text() }}</label>
		<input id="combo-cc-number" data-autoscroll>

		<div class="combo-wiki__card-row">
			<div>
				<label for="combo-cc-expiry">{{ $i18n( 'donate_interface-donor-expiration' ).text() }}</label>
				<input id="combo-cc-expiry" data-autoscroll>
			</div>
			<div>
				<label for="combo-cc-cvv">{{ $i18n( 'donate_interface-donor-security' ).text() }}</label>
				<input id="combo-cc-cvv" data-autoscroll>
			</div>
		</div>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			:disabled="!canSubmit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global SecureFields */
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxField, CdxTextInput, CdxSelect } = require( '@wikimedia/codex' );
const { loadScript } = require( '../utils.js' );

module.exports = exports = defineComponent( {
	name: 'GravyCardForm',

	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput,
		'cdx-field': CdxField,
		'cdx-select': CdxSelect
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
		}
	},

	emits: [ 'presubmit', 'submit', 'error' ],

	data() {
		return {
			// Donor details keyed by the server field names used in formFields and the donate API
			fields: {
				first_name: this.donation.firstName,
				last_name: this.donation.lastName,
				email: this.donation.email,
				street_address: this.donation.streetAddress,
				city: this.donation.city,
				state_province: this.donation.stateProvince,
				postal_code: this.donation.postalCode,
				street_number: this.donation.streetNumber,
				fiscal_number: this.donation.fiscalNumber
			},
			secureFields: null,
			fieldsReady: false,
			formValid: false
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
		canSubmit() {
			return this.formValid && this.detailsComplete;
		},
		detailsComplete() {
			// Only fields this form renders, so e.g. a required country chosen elsewhere doesn't block it
			return Object.keys( this.fields )
				.filter( ( name ) => this.isFieldRequired( name ) )
				.every( ( name ) => this.isFieldComplete( name ) );
		}
	},

	methods: {
		showField( name ) {
			return [ true, 'optional' ].includes( this.formFields[ name ] );
		},
		isFieldRequired( name ) {
			return this.formFields[ name ] === true;
		},
		isFieldComplete( name ) {
			const value = this.fields[ name ];
			if ( name === 'email' ) {
				return this.isValidEmail( value );
			}
			return Boolean( value && value.trim() );
		},
		isValidEmail( email ) {
			return typeof email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() );
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
