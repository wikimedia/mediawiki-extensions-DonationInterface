<template>
	<div class="dp-table__disclaimer">
		<p class="text text--body">
			<span class="text text--body" v-html="donationsDisclaimer"></span>
			<br><br>
			<span class="text text--body" v-html="taxDisclaimer"></span>
		</p>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'DonationsDisclaimerComponent',
    props: {
        email: {
            type: String,
            required: true,
            default: 'N/A'
        },
        helpEmail: {
            type: String,
            required: true
        }
    },
    computed: {
        donationsDisclaimer: function () {
            return this.$i18n( 'donorportal-donations-disclaimer', this.email, `<a href="${ this.helpEmail }" class="link">${ this.helpEmail }</a>` ).text();
        },
        taxDisclaimer: function () {
            return this.$i18n( 'donorportal-tax-disclaimer', `<a href="${ this.helpEmail }" class="link">${ this.helpEmail }</a>` ).text();
        }
    }
} );
</script>
