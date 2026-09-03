<template>
	<component
		:is="variantComponent"
		v-if="variantComponent"
		v-bind="$attrs"
	></component>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { getVariantComponentName } = require( '../variantHelper.js' );

module.exports = exports = defineComponent( {
	name: 'VariantFieldsComponent',
	inject: [ 'params' ],
	inheritAttrs: false,
	data() {
		return {
			variantComponent: getVariantComponentName( this.params.variant )
		};
	}
} );
</script>
