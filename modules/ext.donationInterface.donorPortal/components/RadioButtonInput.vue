<template>
	<div
		id="radio-button-options-list"
		class="oo-ui-fieldLayout"
	>
		<span
			class="oo-ui-widget oo-ui-widget-enabled oo-ui-inputWidget oo-ui-radioInputWidget"
		>
			<input
				:id="`option-${id}`"
				v-model="selectedValue"
				type="radio"
				:name="name"
				:value="value"
				autocomplete="off"
				class="oo-ui-inputWidget-input"
				@input="$emit( 'update:modelValue', $event.target.value )"
			>
		</span>
		<label :for="`option-${id}`">{{ label }}</label>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'RadioButtonInput',
	props: {
		id: {
			type: String,
			required: true
		},
		label: {
			type: String,
			required: true
		},
		value: {
			type: [ String, Number ],
			required: true
		},
		modelValue: {
			type: [ String, Number ],
			required: true
		},
		name: {
			type: String,
			required: true
		}
	},
	emits: [ 'update:modelValue' ],
	setup( props ) {
		const selectedValue = ref( props.modelValue );
		return {
			selectedValue
		};
	}
} );
</script>
