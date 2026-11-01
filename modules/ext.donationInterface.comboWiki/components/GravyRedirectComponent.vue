<template>
	<cdx-button
		:action="donorDetails ? 'progressive' : 'default'"
		:weight="donorDetails ? 'primary' : 'normal'"
		:class="{ 'payment-method-form combo-wiki__button-submit': donorDetails }"
		:data-autoscroll="donorDetails ? null : ''"
		@click="submitDonation"
	>
		{{ buttonLabel }}
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
		buttonLabel() {
			const method = this.paymentMethod;
			if ( ![ 'paypal', 'venmo' ].includes( method ) ) {
				return '';
			}
			// This prop is passed only within the form component, so not one-click section
			if ( this.donorDetails ) {
				return this.$i18n( `combowiki-donate-with-${ method }` ).text();
			}
			// If here, the text is for a button in the one-click section
			return this.$i18n( `combowiki-method-${ method }` ).text();
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
