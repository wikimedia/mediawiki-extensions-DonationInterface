<template>
	<div class="donorportal-recurring-contribution">
		<div>
			<h2>
				{{ contributionAmount }}
			</h2>
			<p>{{ contribution.payment_method }}</p>
			<p>
				{{ recurringNextContributionAmountWithDate }}
			</p>
		</div>
		<div>
			<button class="update-donation">
				{{ $i18n( "donorportal-update-donation-button" ).text() }}
			</button>
			<p v-html="recurringLink"></p>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		contribution: {
			type: Object,
			required: true
		}
	},
	computed: {
		contributionAmount: function () {
			// Frequency keys that can be used here
			// * donorportal-recurring-amount-annual
			// * donorportal-recurring-amount-monthly
			return this.$i18n( this.contribution.amount_frequency_key, this.contribution.amount_formatted, this.contribution.currency ).text();
		},
		recurringNextContributionAmountWithDate: function () {
			return this.$i18n( 'donorportal-recurring-next-amount-and-date', this.contribution.amount_formatted,
				this.contribution.currency, this.contribution.next_sched_contribution_date_formatted ).text();
		},
		recurringLink: function () {
			const pause_link = `<a href="#/pause-donations/${ this.contribution.id }"> ${ this.$i18n( 'donorportal-recurring-pause' ).text() } </a>`;
			const cancel_link = `<a href="#/cancel-donations/${ this.contribution.id }"> ${ this.$i18n( 'donorportal-recurring-cancel' ).text() } </a>`;
			return this.$i18n( 'donorportal-recurring-pause-or-cancel', pause_link, cancel_link ).text();
		}
	}
} );
</script>
