<template>
	<main id="recurring-contribution-cancel-success" class="container column--items-center">
		<section class="column--callout">
			<h1 class="heading heading--h1">
				{{ $i18n( "donorportal-cancel-recurring-confirmation-header" ).text() }}
			</h1>
			<img
				:src="`${ assets_path }/images/wp_symbols_community.svg`"
				alt="Community Icon">
		</section>
		<section class="column--base">
			<ul class="checklist">
				<li class="text text--body" v-html="confirmationText">
				</li>
			</ul>
		</section>

		<section class="column--base">
			<a
				id="shareFeedback"
				target="_blank"
				:href="surveyUrl"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled  cdx-button--weight-primary cdx-button--size-large">
				{{ $i18n( "donorportal-feedback-button" ).text() }}
			</a>
		</section>

		<section class="column--base">
			<p class="text text--body text--align-center">
				<router-link
					id="buttonBackToAccount"
					to="/"
					class="link"
				>
					{{ $i18n( "donorportal-return-to-account-button" ).text() }}
				</router-link>
			</p>
		</section>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
module.exports = exports = defineComponent( {
	name: 'RecurringContributionCancelSuccess',
	components: {
		'router-link': RouterLink
	},
	props: {
		recurringContribution: {
			type: Object,
			required: true
		}
	},
	setup() {
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path,
			surveyUrl: mw.config.get( 'surveyUrl' )
		};
	},
	computed: {
		amountFormated() {
			if ( !this.recurringContribution ) {
				return '';
			}
			// Frequency keys that can be used here
			// * donorportal-recurring-amount-annual
			// * donorportal-recurring-amount-monthly
			return this.$i18n( this.recurringContribution.amount_frequency_key, this.recurringContribution.amount_formatted, this.recurringContribution.currency ).text();
		},
		confirmationText() {
			return this.$i18n( 'donorportal-cancel-monthly-recurring-confirmation-text', `<strong>${ this.amountFormated }</strong>` ).text();
		}
	}
} );
</script>
