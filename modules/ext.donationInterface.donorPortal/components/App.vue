<template>
	<div class="body is-splashscreen">
		<header-component></header-component>
		<router-view></router-view>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const HeaderComponent = require( './Header.vue' );
const { RouterView } = require( 'vue-router' );

module.exports = exports = defineComponent( {
	name: 'DonorPortal',

	components: {
		'header-component': HeaderComponent,
		'router-view': RouterView
	},

	computed: {
	}
} );

</script>
