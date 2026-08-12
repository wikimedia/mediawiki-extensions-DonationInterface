<template>
	<div class="combo-wiki__frequency">
		<cdx-button
			v-for="option in options"
			:key="option.value"
			:class="{ 'combo-wiki__option--selected': modelValue === option.value }"
			@click="$emit( 'update:modelValue', option.value )">
			{{ option.label }}
		</cdx-button>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
  name: 'FrequencySelector',
  components: {
    'cdx-button': CdxButton
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
