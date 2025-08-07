<template>
	<div id="recurring-contribution-pause-success" class="greeting">
		<h2>{{ $i18n( "donorportal-pause-recurring-confirmation-header" ).text() }}</h2>
		<h4>{{ nextContributionDate }}</h4>
		<router-link id="buttonBackToAccount" to="/">
			{{ $i18n( "donorportal-pause-recurring-button" ).text() }}
		</router-link>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
module.exports = exports = defineComponent( {
	name: 'RecurringContributionPauseSuccess',
	components: {
		'router-link': RouterLink
	},
	props: {
		nextSchedContributionDate: {
			type: String,
			required: true,
			defaultValue() {
				return '';
			}
		}
	},
	computed: {
		nextContributionDate() {
			return this.$i18n( 'donorportal-pause-recurring-confirmation-subheader', this.nextSchedContributionDate ).text();
		}
	}
} );
</script>
