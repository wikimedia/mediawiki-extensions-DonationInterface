<template>
	<div class="combo-wiki">
		<payment-form></payment-form>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const PaymentForm = require( '../views/PaymentForm.vue' );

module.exports = exports = defineComponent( {
	name: 'ComboWiki',

	components: {
		'payment-form': PaymentForm
	}
} );
</script>
