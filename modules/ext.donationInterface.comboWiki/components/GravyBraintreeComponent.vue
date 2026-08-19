<template>
	<cdx-button
		action="progressive"
		weight="primary"
		@click="$emit( 'submit' )"
	>
		{{ donateText }}
	</cdx-button>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'GravyBraintreeComponent',

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
			// should only have paypal or venmo for braintree
			return '';
		}
	}
} );
</script>
