<template>
	<section v-if="recurringContributionValuesSet" class="column--base contribution-details">
		<section :class="boxClass">
			<div class="box__inner">
				<h2 class="heading heading--h1">
					{{ recurringContributionAmount }}
				</h2>
				<p class="text text--body">
					{{ recurringContribution.payment_method }}<br>
					{{ nextContributionDate }}
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
		},
		extraClasses: {
			type: String
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
		nextContributionDate() {
			let nextDate = 'N/A';
			if ( this.recurringContribution && this.recurringContribution.next_sched_contribution_date_formatted ) {
				nextDate = this.recurringContribution.next_sched_contribution_date_formatted;
			}
			return this.$i18n( 'donorportal-pause-recurring-next-donation-date', nextDate ).text();
		},
		boxClass: function () {
			let boxClass = 'box';
			if ( this.extraClasses ) {
				boxClass = `${ boxClass } ${ this.extraClasses }`;
			}
			return boxClass;
		}
	}
} );
</script>
