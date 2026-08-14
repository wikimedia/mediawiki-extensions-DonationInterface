<template>
	<p :class="textClass">
		<template v-for="( part, index ) in parts" :key="index">
			<a
				v-if="part.type === 'link'"
				:href="part.href"
				:target="part.external ? '_blank' : null"
				:rel="part.external ? 'noopener noreferrer' : null">
				{{ part.text }}
			</a>
			<template v-else>
				{{ part.text }}
			</template>
		</template>
	</p>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'MoreInfoLinks',

	props: {
		textClass: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			ProblemsUrl: 'https://donate.wikimedia.org/wiki/Special:LandingCheck?landing_page=Problems_donating&amp;basic=true&amp;language=en&amp;country=NO',
			WayToGive: 'https://donate.wikimedia.org/wiki/Special:LandingCheck?basic=true&amp;landing_page=Ways_to_Give&amp;language=en',
			FrequentlyAskedQuestions: 'https://wikimediafoundation.org/give/donor-frequently-asked-questions/',
			TaxDeductibilityInfo: 'https://wikimediafoundation.org/give/donor-frequently-asked-questions/#tax-deductibility'
		};
	},
	computed: {
		parts() {
			return [
				{ type: 'link', text: this.$i18n( 'donate_interface-problemsdonating' ).text(), href: this.ProblemsUrl, external: true },
				{ type: 'link', text: this.$i18n( 'donate_interface-otherways-short' ).text(), href: this.WayToGive, external: true },
				{ type: 'link', text: this.$i18n( 'donate_interface-faqs' ).text(), href: this.FrequentlyAskedQuestions, external: true },
				{ type: 'link', text: this.$i18n( 'donate_interface-tax-info' ).text(), href: this.TaxDeductibilityInfo, external: true }
			];
		}
	}
} );
</script>
