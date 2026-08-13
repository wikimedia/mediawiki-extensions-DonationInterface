<template>
	<div class="combo-wiki__loading-spinner" :class="{ visible: loading, hidden: !loading }">
		<img id="loading" :src="overlayIconURL">
	</div>
</template>

<script>
const { defineComponent, ref, watch } = require( 'vue' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'LoadingSpinner',
	setup() {
		const { loading } = useAppState();
		return {
			loading,
			overlayIconURL: `${ mw.config.get( 'script_path' ) }/extensions/DonationInterface/gateway_forms/includes/loading-black.gif`
		};
	}
} );
</script>
