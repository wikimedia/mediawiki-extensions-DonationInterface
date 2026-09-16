<template>
	<div class="combo-wiki">
		<forbidden-container v-if="isForbidden"></forbidden-container>
		<payment-form v-else></payment-form>
	</div>
</template>

<script>
const { defineComponent, computed } = require( 'vue' );
const PaymentForm = require( '../views/PaymentForm.vue' );
const ForbiddenContainer = require( '../views/forbidden/ForbiddenPageContainer.vue' );
module.exports = exports = defineComponent( {
	name: 'ComboWiki',

	components: {
		'payment-form': PaymentForm,
		'forbidden-container': ForbiddenContainer
	},

	setup() {
		const isForbidden = computed( () => !!( typeof mw !== 'undefined' && mw.config && mw.config.get( 'wgForbiddenViewType' ) ) );

		return {
			isForbidden
		};
	}
} );
</script>
