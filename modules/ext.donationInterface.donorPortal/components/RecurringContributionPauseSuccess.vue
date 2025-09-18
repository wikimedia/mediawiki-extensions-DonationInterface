<template>
	<main id="recurring-contribution-pause-success" class="greeting container column--items-center">
		<section class="container__inner dp-dashboard__intro">
			<router-link
				id="buttonBackToAccount"
				to="/"
				class="text text--body link link--muted link--no-underline">
				← <span class="link--underline">{{ $i18n( "donorportal-return-to-account-button" ).text() }}</span>
			</router-link>
			<h1 class="heading heading--subtle heading--h2">
				{{ $i18n( "donorportal-pause-recurring-confirmation-header" ).text() }}
			</h1>
			<p class="text text--body" v-html="nextContributionDate"></p>
		</section>
	</main>
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
			return this.$i18n( 'donorportal-pause-recurring-confirmation-subheader', `<strong>${ this.nextSchedContributionDate }</strong>` ).text();
		}
	}
} );
</script>
