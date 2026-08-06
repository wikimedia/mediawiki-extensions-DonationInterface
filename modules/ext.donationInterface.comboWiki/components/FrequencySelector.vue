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
			return [
				{ value: 'once', label: this.$i18n( 'donate_interface-onetime-short' ).text() },
				{ value: 'monthly', label: this.$i18n( 'donate_interface-monthly-short' ).text() },
				{ value: 'annual', label: this.$i18n( 'combowiki-frequency-annual' ).text() }
			];
		}
	}
} );
</script>
