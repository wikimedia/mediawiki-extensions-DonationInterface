<template>
	<main class="combo-wiki__home">
		<p class="combo-wiki__intro">
			ComboWiki: single-page donation flow (placeholder).
		</p>
		<!-- TODO: replace these placeholders with the real accordion steps. -->
		<ol class="combo-wiki__steps">
			<li>Step 1 - Gift amount</li>
			<li>Step 2 - Frequency</li>
			<li>Step 3 - Payment method (script-load / consent trigger)</li>
			<li>Step 4 - Consent toggles</li>
		</ol>
	</main>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'Home'
} );
</script>
