<template>
	<div class="combo-wiki__employer">
		<cdx-field :is-required="false">
			<cdx-label input-id="combo-employer-name">
				{{ employerLabel }}
			</cdx-label>
			<cdx-text-input
				id="combo-employer-name"
				:model-value="modelValue"
				@update:model-value="updateEmployer">
			</cdx-text-input>
		</cdx-field>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxLabel, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = defineComponent( {
	name: 'EmployerField',
	components: {
		'cdx-field': CdxField,
		'cdx-label': CdxLabel,
		'cdx-text-input': CdxTextInput
	},
	props: {
		modelValue: {
			type: String,
			default: ''
		}
	},
	emits: [ 'update:modelValue' ],
	computed: {
		employerLabel() {
			return this.$i18n( 'donate_interface-donor-employer' ).text();
		}
	},
	methods: {
		updateEmployer( value ) {
			const sanitized = ( value || '' ).trimStart();
			this.$emit( 'update:modelValue', sanitized );
		}
	}
} );
</script>
