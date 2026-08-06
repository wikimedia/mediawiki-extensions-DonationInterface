<template>
	<div class="combo-wiki">
		<forbidden-container v-if="isForbidden"></forbidden-container>
		<div v-else>
			<header-component></header-component>
			<payment-form></payment-form>
		</div>
	</div>
</template>

<script>
const { defineComponent, computed } = require( 'vue' );
const PaymentForm = require( '../views/PaymentForm.vue' );
const ForbiddenContainer = require( '../views/forbidden/ForbiddenPageContainer.vue' );
const HeaderComponent = require( '../views/Header.vue' );

module.exports = exports = defineComponent( {
	name: 'ComboWiki',

	components: {
		'payment-form': PaymentForm,
		'forbidden-container': ForbiddenContainer,
		'header-component': HeaderComponent
	},

	setup() {
		const isForbidden = computed( () => !!( typeof mw !== 'undefined' && mw.config && mw.config.get( 'wgForbiddenViewType' ) ) );

		return {
			isForbidden
		};

	}
} );
</script>
