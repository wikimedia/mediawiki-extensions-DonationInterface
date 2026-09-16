<template>
	<div v-if="error" class="combo-wiki__error-display">
		<div class="combo-wiki__error-display-box">
			<p class="combo-wiki__error-display-message">{{ error }}</p>
				<cdx-button weight="primary" @click="reloadPage">
					Try Again
				</cdx-button>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { useAppState } = require( '../composables/useAppState.js' );
module.exports = exports = defineComponent( {
	name: 'ErrorDisplay',
	components: {
		'cdx-button': CdxButton
	},
	setup() {
		const { error } = useAppState();
		return {
			error
		};
	},
	methods: {
		reloadPage() {
			// clear debug param if its there
			const url = new URL( window.location.href );
			url.searchParams.delete( 'debugError' );
			window.history.replaceState( {}, '', url );
			window.location.reload();
		}
	}
} );

</script>
