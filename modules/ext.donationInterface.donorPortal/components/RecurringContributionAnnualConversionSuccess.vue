<template>
	<main id="recurring-contribution-annual-conversion-success" class="container column--items-center">
		<section class="column--callout">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-thank-you" ).text() }}
			</h1>
			<img
				:src="`${ assets_path }/images/wp_symbols_community.svg`"
				alt="Community Icon">
		</section>
		<section class="column--base">
			<ul class="checklist">
				<li class="text text--body" v-html="conversionSuccessToYearly">
				</li>
				<li class="text text--body" v-html="conversionSuccessOnSchedule">
				</li>
			</ul>
		</section>
		<section class="column--base">
			<router-link
				id="buttonBackToAccount"
				to="/"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled  cdx-button--weight-primary cdx-button--size-large"
			>
				{{ $i18n( "donorportal-return-to-account-button" ).text() }}
			</router-link>
		</section>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );

module.exports = exports = defineComponent( {
	name: 'RecurringContributionAnnualConversionSuccess',
	components: {
		'router-link': RouterLink
	},
	props: {
		nextYearlySchedContributionDateFormatted: {
			type: String,
			required: true,
			defaultValue() {
				return '';
			}
		}
	},
	setup() {
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	},
	computed: {
		conversionSuccessToYearly() {
			return this.$i18n( 'donorportal-update-recurring-yearly-conversion-success' ).text();
		},
		conversionSuccessOnSchedule() {
			return this.$i18n( 'donorportal-update-recurring-yearly-conversion-on-schedule', `<strong>${ this.nextYearlySchedContributionDateFormatted }</strong>` ).text();
		}
	}
} );
</script>
