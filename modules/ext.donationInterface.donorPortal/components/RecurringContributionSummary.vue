<template>
	<div v-if="recurringContributionValuesSet" class="contribution-details">
		<div>
			<p>{{ recurringContributionAmount }}</p>
			<p>{{ recurringContribution.payment_method }}</p>
			<p>{{ lastContributionDate }}</p>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'RecurringContributionSummary',
	props: {
		recurringContribution: {
			type: Object,
			required: true
		}
	},
	computed: {
		recurringContributionValuesSet() {
			return Object.keys( this.recurringContribution ).length > 0;
		},
		recurringContributionAmount() {
			// Amount frequency keys that can be used here
			// * donorportal-recurring-amount-annual
			// * donorportal-recurring-amount-monthly
			return this.$i18n( this.recurringContribution.amount_frequency_key, this.recurringContribution.amount_formatted, this.recurringContribution.currency ).text();
		},
		lastContributionDate() {
			let lastDate = 'N/A';
			if ( this.recurringContribution && this.recurringContribution.last_contribution_date_formatted ) {
				lastDate = this.recurringContribution.last_contribution_date_formatted;
			}
			return this.$i18n( 'donorportal-pause-recurring-last-donation-date', lastDate ).text();
		}
	}
} );
</script>
