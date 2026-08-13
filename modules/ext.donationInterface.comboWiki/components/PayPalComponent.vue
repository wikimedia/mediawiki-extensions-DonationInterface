<template>
	<cdx-button
		v-if="donation.paymentMethod === 'paypal'"
		action="progressive"
		weight="primary"
		:disabled="!giftComplete"
		@click="$emit( 'submit' )">
		{{ $i18n( 'combowiki-donate-with-paypal' ).text() }}
	</cdx-button>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
    name: 'GravyPayPal',

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
        giftComplete() {
            return this.donation.frequency !== null && this.donation.amount !== null;
        }
    }
} );
</script>
