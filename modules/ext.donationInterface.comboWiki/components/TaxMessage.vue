<template>
	<p v-html="textMessage"></p>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'TaxMessage',

	props: {
		countryCode: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			tax_url: 'https://wikimediafoundation.org/give/donor-frequently-asked-questions/#tax-deductibility',
			problems_email: 'donate@wikimedia.org'
		};
	},
	computed: {
		textMessage() {
			const countryCode = this.countryCode;
			if ( countryCode === 'US' ) {
				return `<a href="${ this.tax_url }" target="_blank" class="link">${ this.$i18n( 'donate_interface-tax-info' ) }</a>`;
			} else if ( countryCode === 'FR' ) {
				const countryName = mw.config.get( 'wgDonationInterfaceCountries', {} )[ this.countryCode ].label || 'France';
				return this.$i18n( 'donate_interface-taxded-msg-x', countryName, this.tax_url, this.problems_email ).text();
			} else if ( countryCode === 'NL' ) {
				return this.$i18n( 'donate_interface-taxded-msg-y', this.tax_url ).text();
			} else {
				return this.$i18n( 'donate_interface-taxded-msg-z', this.tax_url ).text();
			}
		}
	}
} );
</script>
