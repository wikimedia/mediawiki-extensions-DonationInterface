<template>
	<div class="greeting">
		<h2>{{ $i18n( "donorportal-greeting", name ).text() }}</h2>
		<p><strong>{{ $i18n( "donorportal-boldtext" ).text() }}</strong></p>
		<p>{{ $i18n( "donorportal-smalltext" ).text() }}</p>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
module.exports = exports = defineComponent( {
	props: {
		name: {
			type: String,
			required: false,
			default() {
				return '';
			}
		}
	}
} );
</script>
