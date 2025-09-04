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
			<a href="https://donate.wikimedia.org/?wmf_medium=DonorPortal&wmf_source=DonorPortal&wmf_campaign=DonorPortal" class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--weight-primary cdx-button--size-large">
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
	computed: {
		lastAmountAndDate: function () {
			return this.$i18n( 'donorportal-last-amount-and-date', this.contribution.last_amount_formatted,
				this.contribution.last_currency, this.contribution.last_receive_date_formatted ).text();
		}
	}
} );
</script>
