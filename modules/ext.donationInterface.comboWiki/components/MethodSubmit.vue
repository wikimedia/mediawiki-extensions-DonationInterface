<template>
	<!-- A payment method's submit button (or wallet button).
	When the method's needs an extra form (not instant), PaymentMethodForm provides
	a spot below the box and the button renders there; otherwise it stays where it is. -->
	<teleport :to="target" :disabled="!target">
		<slot></slot>
	</teleport>
</template>

<script>
const { defineComponent, inject, ref } = require( 'vue' );

module.exports = exports = defineComponent( {
	name: 'MethodSubmit',
	setup() {
		// The element below the box, or null to render in place.
		// An element, not a selector: the target renders in the same view, and Vue 3.4 has no Teleport defer.
		const target = inject( 'methodSubmitTarget', ref( null ) );
		return { target };
	}
} );
</script>
