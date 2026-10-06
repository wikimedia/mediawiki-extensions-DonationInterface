<template>
	<div class="combo-wiki__sepa payment-method-form">
		<div class="combo-wiki__sepa-fields">
			<div class="combo-wiki__sepa-fields-full-name">
				<cdx-field v-if="showField( 'first_name' )" :optional="!isFieldRequired( 'first_name' )">
					<cdx-text-input
						v-model="fields.first_name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
					></cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-first_name' ).text() }}
					</template>
				</cdx-field>
				<cdx-field v-if="showField( 'last_name' )" :optional="!isFieldRequired( 'last_name' )">
					<cdx-text-input
						v-model="fields.last_name"
						data-autoscroll
						:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
					></cdx-text-input>
					<template #label>
						{{ $i18n( 'donate_interface-donor-last_name' ).text() }}
					</template>
				</cdx-field>
			</div>
			<cdx-field v-if="showField( 'email' )" :optional="!isFieldRequired( 'email' )">
				<cdx-text-input
					v-model="fields.email"
					type="email"
					data-autoscroll
					autocomplete="email"
				></cdx-text-input>
				<template #label>
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</template>
			</cdx-field>
		</div>

		<p v-if="donation.frequency === 'once'">
			{{ $i18n( 'donate_interface-sepa-mandate' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'monthly'">
			{{ $i18n( 'donate_interface-sepa-mandate-monthly' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'annual'">
			{{ $i18n( 'donate_interface-sepa-mandate-yearly' ).text() }}
		</p>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			:disabled="isSubmitting || !canSubmit"
			@click="submit"
		>
			{{ donateBtn }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxField, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'SEPAComponent',

	components: {
		CdxButton,
		CdxField,
		CdxTextInput
	},

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

	emits: [ 'submit', 'error' ],

	data() {
		return {
			// Donor details keyed by the server field names used in formFields and the donate API
			fields: {
				first_name: this.donation.firstName,
				last_name: this.donation.lastName,
				email: this.donation.email
			},
			isSubmitting: false
		};
	},

	computed: {
		canSubmit() {
			return this.detailsComplete;
		},
		detailsComplete() {
			// Only fields this form renders, so e.g. a required country chosen elsewhere doesn't block it
			return Object.keys( this.fields )
				.filter( ( name ) => this.isFieldRequired( name ) )
				.every( ( name ) => this.isFieldComplete( name ) );
		},
		donateBtn() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-submit-button' ).text();
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
		handleSubmitFailure() {
			this.isSubmitting = false;
		},
		submit() {
			if ( this.isSubmitting || !this.canSubmit ) {
				return;
			}

			this.isSubmitting = true;
			this.$emit(
				'submit',
				Object.assign( {}, this.fields ),
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
