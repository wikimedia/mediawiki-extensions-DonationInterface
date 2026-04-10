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
		errorCode: {
			type: String,
			required: false,
			default: null
		},
		fallbackMessageKey: {
			type: String,
			required: false,
			default: 'donorportal-cancel-failure'
		}
	},
	setup() {
		const assets_path = mw.config.get( 'assets_path' );
		return {
			assets_path
		};
	},
	computed: {
		failureMessage: function () {
			const errorMessageMap = {
				'no-session': mw.message( 'donorportal-error-no-session' ).text(),
				'bad-contact-id': mw.message( 'donorportal-error-bad-contact-id', mw.config.get( 'help_email' ) ).text(),
				'bad-contribution-recur-id': mw.message( 'donorportal-error-bad-contribution-recur-id', mw.config.get( 'help_email' ) ).text()
			};
			if ( this.errorCode && errorMessageMap[ this.errorCode ] ) {
				return errorMessageMap[ this.errorCode ];
			}
			// Messages that can be used here:
			// * donorportal-cancel-failure
			// * donorportal-pause-failure
			return this.$i18n( this.fallbackMessageKey, mw.config.get( 'help_email' ) ).text();
		}
	}
} );
</script>
