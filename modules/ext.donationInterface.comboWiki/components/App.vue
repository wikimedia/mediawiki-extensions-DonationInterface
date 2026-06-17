<template>
	<div class="combo-wiki">
		<home></home>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const Home = require( '../views/Home.vue' );

module.exports = exports = defineComponent( {
	name: 'ComboWiki',

	components: {
		'home': Home
	}
} );
</script>
