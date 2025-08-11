<template>
	<div class="donorportal-inactive-recurring">
		<div>
			<h2>{{ $i18n( "donorportal-renew-support" ).text() }}</h2>
			<p>{{ contribution.payment_method }}</p>
			<p v-if="contribution.hasLastContribution">
				{{ $i18n( "donorportal-last-amount-and-date",
					contribution.amount_formatted, contribution.currency, contribution.last_contribution_date_formatted
				).text() }}
			</p>
		</div>
		<div>
			<button class="renew-donation">
				{{ renewButtonText }}
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
		renewButtonText: function () {
			return this.$i18n( this.contribution.restart_key ).text();
		}
	}
} );
</script>
