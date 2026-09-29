<template>
	<div class="combo-wiki__frequency">
		<cdx-toggle-button-group
			:model-value="modelValue"
			:buttons="options"
			@update:model-value="$emit( 'update:modelValue', $event )"
		></cdx-toggle-button-group>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxToggleButtonGroup } = require( '@wikimedia/codex' );
const { getFrequencyOptions } = require( '../frequencyOptions.js' );

module.exports = exports = defineComponent( {
	name: 'FrequencySelector',
	components: {
		'cdx-toggle-button-group': CdxToggleButtonGroup
	},
	props: {
		modelValue: {
			type: String,
			default: null
		}
	},
	emits: [ 'update:modelValue' ],
	computed: {
		options() {
			return getFrequencyOptions();
		}
	}
} );
</script>
