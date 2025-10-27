<template>
	<div class="dp-card__appeal donorportal-recent-donation">
		<div class="dp-card__section dp-card__summary">
			<p class="text heading--h2">
				<strong>{{ contribution.last_amount_formatted }} {{ contribution.last_currency }}</strong>
			</p>
			<p class="text text--body">
				{{ lastAmountAndDate }}
			</p>
		</div>
		<div class="dp-card__section dp-card__cta">
			<a
				:href="newDonationUrl"
				target="_blank">
				{{ $i18n( "donorportal-new-donation" ).text() }}
			</a>
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
	setup() {
		return {
			newDonationUrl: mw.config.get( 'newDonationUrl' )
		};
	},
	computed: {
		lastAmountAndDate: function () {
			return this.$i18n( 'donorportal-last-amount-and-date', this.contribution.last_amount_formatted,
				this.contribution.last_currency, this.contribution.last_receive_date_formatted ).text();
		}
	}
} );
</script>
