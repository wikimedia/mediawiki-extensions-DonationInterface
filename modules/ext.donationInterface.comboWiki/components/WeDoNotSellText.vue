<template>
	<p v-html="weDoNotSellShareInfo"></p>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { openLinksInNewTab } = require( '../utils.js' );

module.exports = exports = defineComponent( {
	name: 'WeDoNotSellText',

	data() {
		return {
			policyUrl: 'https://foundation.wikimedia.org/wiki/Privacy_policy'
		};
	},
	computed: {
		weDoNotSellShareInfo() {
			return openLinksInNewTab( this.$i18n( 'donate_interface-informationsharing', this.policyUrl ).text() );
		}
	}
} );
</script>
