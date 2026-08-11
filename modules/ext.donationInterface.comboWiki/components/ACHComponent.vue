<template>
	<div class="combo-wiki__ach">
		<div class="combo-wiki__ach-fields">
			<cdx-field :is-required="true">
				<cdx-label input-id="combo-employer-name">{{ emailLabel }}</cdx-label>
				<cdx-text-input
					id="combo-ach-email"
					v-model="donation.email"
					type="email"
					autocomplete="email"
				/>
			</cdx-field>
		</div>

		<h4>{{ signInBankLabel }}</h4>

		<p>{{ trustlyInfo }}</p>

		<p>
			<img
				class="combo-wiki__trustly-img"
				:alt="trustlyAlt"
				src="/extensions/DonationInterface/gateway_forms/includes/trustly.png"
			>
		</p>

		<cdx-button
			action="progressive"
			weight="primary"
			:disabled="isSubmitting || !canSubmit"
			@click="submit"
		>
			{{ isSubmitting ? '...' : signInBank }}
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
			isSubmitting: false
		};
	},

	computed: {
		canSubmit() {
			return this.isValidEmail( this.donation.email );
		},
		emailLabel() {
			return this.$i18n( 'donate_interface-donor-email' ).text();
		},
		signInBankLabel() {
			return this.$i18n( 'donate_interface-sign-in-online-banking' ).text();
		},
		signInBank() {
			return this.$i18n( 'donate_interface-sign-in-to-my-bank' ).text();
		},
		trustlyInfo() {
			return this.$i18n( 'donate_interface-partner-with-trustly' ).text();
		},
		trustlyAlt() {
			return this.$i18n( 'donate_interface-pay-with-trustly-alt' ).text();
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
					email: this.donation.email,
				},
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
