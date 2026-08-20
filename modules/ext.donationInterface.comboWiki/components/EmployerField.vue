<template>
	<div class="combo-wiki__employer">
		<cdx-field :is-required="false">
			<cdx-label input-id="combo-employer-name">
				{{ employerLabel }}
			</cdx-label>
			<cdx-text-input
				id="combo-employer-name"
				:model-value="modelValue"
				@update:model-value="updateEmployer"
			>
			</cdx-text-input>
			<template #help-text>
				{{ employerExplain }}
			</template>
		</cdx-field>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxField, CdxLabel, CdxTextInput } = require( '@wikimedia/codex' );
const { stripLeadingWhitespace } = require( '../normalizeInput.js' );

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
		},
		employerExplain() {
			return this.$i18n( 'donate_interface-donor-employer-explain' ).text();
		}
	},
	methods: {
		updateEmployer( value ) {
			this.$emit( 'update:modelValue', stripLeadingWhitespace( value ) );
		}
	}
} );
</script>
