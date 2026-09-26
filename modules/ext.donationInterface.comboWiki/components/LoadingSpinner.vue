<template>
	<div class="combo-wiki__loading-spinner" :class="{ visible: loading, hidden: !loading }">
		<img id="loading" :src="overlayIconURL">
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'LoadingSpinner',
	setup() {
		const { inject } = require( 'vue' );
		const params = inject( 'params' );
		const { loading } = useAppState();
		return {
			loading,
			overlayIconURL: `${ params.script_path }/extensions/DonationInterface/gateway_forms/includes/loading-black.gif`
		};
	}
} );
</script>
