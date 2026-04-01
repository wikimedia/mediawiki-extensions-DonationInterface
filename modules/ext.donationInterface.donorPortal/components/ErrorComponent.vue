<template>
	<main id="error-component" class="container column--items-center">
		<section class="column--callout">
			<img
				:src="`${ assets_path }/images/wp_symbols_community.svg`"
				alt="Community Icon">
		</section>
		<section class="column--callout">
			<div class="site-content">
				<h2>{{ failureMessage }}</h2>
			</div>
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
	name: 'ErrorComponent',
	components: {
		'router-link': RouterLink
	},
	props: {
		failureMessage: {
			type: String,
			required: true
		}
	},
	setup() {
		const assets_path = mw.config.get( 'assets_path' );

		return {
			assets_path
		};
	}
} );
</script>
