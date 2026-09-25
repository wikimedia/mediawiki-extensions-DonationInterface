<template>
	<div class="forbidden-container">
		<header-component></header-component>
		<component :is="resolvedComponent"></component>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const DefaultForbiddenNotice = require( './views/DefaultForbiddenNotice.vue' );
const RussiaNotice = require( './views/RussiaNotice.vue' );
const FinlandNotice = require( './views/FinlandNotice.vue' );
const HeaderComponent = require( './../Header.vue' );

const componentMap = {

	russia_notice: RussiaNotice,
	finland_notice: FinlandNotice
};

module.exports = exports = defineComponent( {
	name: 'ForbiddenPageContainer',

	components: {
		HeaderComponent,
		DefaultForbiddenNotice
	},

	setup() {

	},
	computed: {
		resolvedComponent() {
			const viewType = mw.config.get( 'wgForbiddenViewType' );
			return componentMap[ viewType ] || DefaultForbiddenNotice;
		}
	}
} );
</script>
