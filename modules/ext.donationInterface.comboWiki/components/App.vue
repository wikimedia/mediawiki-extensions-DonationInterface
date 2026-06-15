<template>
	<div class="combo-wiki">
		<router-view></router-view>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { RouterView } = require( 'vue-router' );

module.exports = exports = defineComponent( {
	name: 'ComboWiki',

	components: {
		'router-view': RouterView
	}
} );
</script>
