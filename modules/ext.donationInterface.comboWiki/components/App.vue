<template>
	<div class="combo-wiki">
		<header-component></header-component>
		<forbidden-container v-if="isForbidden"></forbidden-container>
		<payment-form v-else></payment-form>
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
		const { inject } = require( 'vue' );
		const params = inject( 'params' );
		const isForbidden = computed( () => !!params.wgForbiddenViewType );

		return {
			isForbidden
		};

	}
} );
</script>
