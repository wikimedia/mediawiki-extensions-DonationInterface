<template>
	<div class="combo-wiki__ach payment-method-form">
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
			:disabled="isSubmitting"
			@click="submit"
		>
			{{ signInBank }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'ACHComponent',

	components: {
		CdxButton
	},

	props: {
		// Unused, but PaymentMethodForm passes it to every payment method form,
		// so declaring it keeps it from falling through as a DOM attribute
		// eslint-disable-next-line vue/no-unused-properties
		donation: {
			type: Object,
			required: true
		},
		// The details form above this one, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},

	emits: [ 'submit', 'error' ],

	data() {
		return {
			isSubmitting: false
		};
	},

	computed: {
		signInBank() {
			if ( this.isSubmitting ) {
				return '...';
			}
			return this.$i18n( 'donate_interface-sign-in-to-my-bank' ).text();
		}
	},

	methods: {
		handleSubmitFailure() {
			this.isSubmitting = false;
		},
		submit() {
			if ( this.isSubmitting || ( this.donorDetails && !this.donorDetails.validate() ) ) {
				return;
			}

			this.isSubmitting = true;
			this.$emit(
				'submit',
				{ payment_submethod: 'ach' },
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
