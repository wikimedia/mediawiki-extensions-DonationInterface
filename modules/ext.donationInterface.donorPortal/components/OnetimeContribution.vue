<template>
	<div class="donorportal-recent-donation">
		<div>
			<h2>{{ contribution.last_amount_formatted }} {{ contribution.last_currency }}</h2>
			<p>{{ contribution.last_payment_method }}</p>
			<p>
				{{ lastAmountAndDate }}
			</p>
		</div>
		<div>
			<button class="new-donation">
				{{ $i18n( "donorportal-new-donation" ).text() }}
			</button>
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
