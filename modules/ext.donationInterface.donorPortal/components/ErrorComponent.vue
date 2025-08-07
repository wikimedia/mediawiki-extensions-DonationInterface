<template>
	<div id="error-component" class="site-content">
		<h2>{{ failureMessage }}</h2>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'ErrorComponent',
	props: {
		failureMessage: {
			type: String,
			required: true
		}
	}
} );
</script>
