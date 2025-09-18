<template>
	<section v-if="recurringContributionValuesSet" class="column--base contribution-details">
		<section class="box is-lapsed">
			<div class="box__inner">
				<h2 class="heading heading--h1">
					{{ recurringContributionAmount }}
				</h2>
				<p class="text text--body">
					{{ recurringContribution.payment_method }}<br>
					{{ lastContributionDate }}
				</p>
			</div>
		</section>
	</section>
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
