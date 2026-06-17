<template>
  <div class="combo-wiki__frequency">
    <cdx-button
      v-for="option in options"
      :key="option.value"
      :class="{ 'combo-wiki__option--selected': modelValue === option.value }"
      @click="$emit( 'update:modelValue', option.value)">
      {{ option.label }}
    </cdx-button>
  </div>
</template>

<script>
const { defineComponent } = require( 'vue');
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
  data() {
    return {
      options: [
        { value: 'once', label: 'One time' },
        { value: 'monthly', label: 'Monthly' },
        { value: 'annual', label: 'Annual' },
      ]
    }
  }
} );
</script>
