<template>
	<cdx-button
		action="progressive"
		weight="primary"
		@click="submitDonation"
	>
		{{ donateText }}
	</cdx-button>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'GravyRefirectComponent',

	components: {
		'cdx-button': CdxButton
	},

	props: {
		donation: {
			type: Object,
			required: true
		}
	},
	emits: [ 'submit' ],

	computed: {
		donateText() {
			if ( this.donation.paymentMethod === 'paypal' ) {
				return this.$i18n( 'combowiki-donate-with-paypal' ).text();
			}
			if ( this.donation.paymentMethod === 'venmo' ) {
				return this.$i18n( 'combowiki-donate-with-venmo' ).text();
			}
			// should only have paypal or venmo for redirect
			return '';
		}
	},

	methods: {
		submitDonation() {
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
