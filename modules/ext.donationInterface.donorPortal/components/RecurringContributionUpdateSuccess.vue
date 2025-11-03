<template>
	<main id="recurring-contribution-update-success" class="container column--items-center">
		<section class="column--callout">
			<h1 class="heading heading--h1">
				{{ $i18n( 'donorportal-update-recurring-confirmation-header' ).text() }}
				<br>
				{{ $i18n( 'donorportal-update-recurring-confirmation-header-subtitle' ).text() }}
			</h1>
			<img
				:src="`${ assets_path }/images/wp_symbols_community.svg`"
				alt="Community Icon">
		</section>
		<section class="column--base">
			<ul class="checklist">
				<li class="text text--body" v-html="confirmationText">
				</li>
			</ul>
		</section>
		<section class="column--base">
			<router-link
				id="buttonBackToAccount"
				to="/"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled  cdx-button--weight-primary cdx-button--size-large"
			>
				{{ $i18n( "donorportal-return-to-account-button" ).text() }}
			</router-link>
		</section>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { RouterLink } = require( 'vue-router' );
module.exports = exports = defineComponent( {
	name: 'RecurringContributionUpdateSuccess',
	components: {
		'router-link': RouterLink
	},
	props: {
    nextSchedContributionDate: {
			type: String,
			required: true
		},
    newAmount: {
			type: String,
			required: true
		}
	},
	setup() {
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	},
	computed: {
		confirmationText() {
			return this.$i18n( 'donorportal-update-recurring-confirmation-text', `<strong>${ this.newAmount }</strong>`, `<strong>${ this.nextSchedContributionDate }</strong>` ).text();
		}
	}
} );
</script>
