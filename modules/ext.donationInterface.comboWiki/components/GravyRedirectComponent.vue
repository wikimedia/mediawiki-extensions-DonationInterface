<template>
	<!-- Below a details form (e.g. state in AU), so it reads as the submit button -->
	<div v-if="donorDetails" class="payment-method-form">
		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			@click="submitDonation"
		>
			{{ donateLabel }}
		</cdx-button>
	</div>
	<cdx-button
		v-else
		data-autoscroll
		class="cdx-button cdx-button--action-progressive cdx-button--weight-primary cdx-button--size-medium cdx-button--framed combo-wiki__button-submit"
		@click="submitDonation"
	>
		{{ label }}
	</cdx-button>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'GravyRedirectComponent',

	components: {
		'cdx-button': CdxButton
	},

	props: {
		donation: {
			type: Object,
			required: true
		},
		paymentMethod: {
			type: String,
			required: true
		},
		// The details form above this one, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},
	emits: [ 'submit' ],

	computed: {
		// Just the method name, like the other payment method buttons
		label() {
			if ( this.paymentMethod === 'paypal' ) {
				return this.$i18n( 'combowiki-method-paypal' ).text();
			}
			if ( this.paymentMethod === 'venmo' ) {
				return this.$i18n( 'combowiki-method-venmo' ).text();
			}
			// should only have paypal or venmo for redirect
			return '';
		},
		// Below the fields, so it reads as the submit button rather than another method to pick
		donateLabel() {
			if ( this.paymentMethod === 'paypal' ) {
				return this.$i18n( 'combowiki-donate-with-paypal' ).text();
			}
			if ( this.paymentMethod === 'venmo' ) {
				return this.$i18n( 'combowiki-donate-with-venmo' ).text();
			}
			return '';
		}
	},

	methods: {
		submitDonation() {
			if ( this.donorDetails && !this.donorDetails.validate() ) {
				return;
			}
			if ( this.donation.frequency === 'once' ) {
				const appState = useAppState();
				// todo: Should add config to see if recurring convert config enabled after
				appState.setShowRecurringConvert( true );
			} else {
				this.$emit( 'submit' );
			}
		}
	}
} );
</script>
