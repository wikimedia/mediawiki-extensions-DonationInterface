<template>
	<div id="radio-button-options-list" class="cdx-radio">
		<div class="cdx-radio__wrapper">
			<input
				:id="`option-${id}`"
				v-model="selectedValue"
				type="radio"
				:name="name"
				:value="value"
				autocomplete="off"
				class="cdx-radio__input"
				@input="$emit( 'update:modelValue', $event.target.value )"
			>
			<span class="cdx-radio__icon"></span>
			<div class="cdx-radio__label cdx-label">
				<label :for="`option-${id}`" class="cdx-label__label">
					<span class="cdx-label__label__text">{{ label }}</span>
				</label>
			</div>
		</div>
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
