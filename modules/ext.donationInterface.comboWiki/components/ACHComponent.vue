<template>
	<div class="combo-wiki__ach payment-method-form">
		<div class="combo-wiki__ach-fields">
			<cdx-field v-if="showField( 'email' )" :optional="!isFieldRequired( 'email' )">
				<cdx-text-input
					id="combo-ach-email"
					v-model="fields.email"
					data-autoscroll
					type="email"
					autocomplete="email"
				></cdx-text-input>
				<template #label>
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</template>
			</cdx-field>
		</div>

		<h4>{{ $i18n( 'donate_interface-sign-in-online-banking' ).text() }}</h4>

		<p>{{ $i18n( 'donate_interface-partner-with-trustly' ).text() }}</p>

		<p>
			<img
				class="combo-wiki__trustly-img"
				:alt="$i18n( 'donate_interface-pay-with-trustly-alt' ).text()"
				src="/extensions/DonationInterface/gateway_forms/includes/trustly.png"
			>
		</p>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			:disabled="isSubmitting || !canSubmit"
			@click="submit"
		>
			{{ signInBank }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxField, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'ACHComponent',

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
		signInBank() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-sign-in-to-my-bank' ).text();
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
				Object.assign( { payment_submethod: 'ach' }, this.fields ),
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
