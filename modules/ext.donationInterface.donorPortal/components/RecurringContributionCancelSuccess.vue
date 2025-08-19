<template>
	<div id="recurring-contribution-cancel-success" class="greeting">
		<h2>{{ $i18n( "donorportal-cancel-recurring-confirmation-header" ).text() }}</h2>
		<h4>{{ $i18n( "donorportal-cancel-monthly-recurring-confirmation-text", amountFormated ).text() }}</h4>
		<a id="shareFeedback" :href="`mailto:${helpEmail}`">
			{{ $i18n( "donorportal-feedback-button" ).text() }}
		</a>
		<router-link id="buttonBackToAccount" to="/">
			{{ $i18n( "donorportal-return-to-account-button" ).text() }}
		</router-link>
	</div>
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
		helpEmail() {
			return mw.config.get( 'help_email' );
		}
	}
} );
</script>
