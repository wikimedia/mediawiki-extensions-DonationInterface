<template>
	<div class="combo-wiki__ach">
		<div class="combo-wiki__ach-fields">
			<cdx-field :is-required="true">
				<cdx-label input-id="combo-employer-name">
					{{ $i18n( 'donate_interface-donor-email' ).text() }}
				</cdx-label>
				<cdx-text-input
					id="combo-ach-email"
					v-model="email"
					type="email"
					autocomplete="email"
				></cdx-text-input>
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
			:disabled="isSubmitting || !canSubmit"
			@click="submit"
		>
			{{ signInBank }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxField, CdxLabel, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'ACHComponent',

	components: {
		CdxButton,
		CdxField,
		CdxLabel,
		CdxTextInput
	},

	props: {
		donation: {
			type: Object,
			required: true
		}
	},

	emits: [ 'submit', 'error' ],

	data() {
		return {
			email: this.donation.email,
			isSubmitting: false
		};
	},

	computed: {
		canSubmit() {
			return this.isValidEmail( this.email );
		},
		signInBank() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-sign-in-to-my-bank' ).text();
		}
	},

	methods: {
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
				{
					payment_submethod: 'ach',
					email: this.email
				},
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
