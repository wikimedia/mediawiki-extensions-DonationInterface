<template>
	<cdx-lookup
		v-model:selected="selection"
		v-model:input-value="inputValue"
		:menu-items="menuItems"
		clearable="true"
		:placeholder="$i18n( 'combowiki-country-placeholder' ).text()"
	>
	</cdx-lookup>
</template>

<script>
const { defineComponent, ref, computed, watch } = require( 'vue' );
const { CdxLookup } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'CountrySelector',

	components: {
		CdxLookup
	},

	props: {
		modelValue: {
			type: String,
			default: 'US'
		},
		countries: {
			type: Object,
			required: true
		}
	},

	emits: [ 'update:modelValue', 'country-change' ],

	setup( props, { emit } ) {
		// Format options list ({ value: 'US', label: 'United States (USD)' })
		const countryOptions = computed( () => Object.entries( props.countries ).map( ( [ code, config ] ) => ( {
			value: code,
			label: `${ config.label } (${ config.currency })`
		} ) )
		);

		// Find prefilled match
		const initialMatch = countryOptions.value.find(
			( item ) => item.value === props.modelValue
		);

		// Initialize state using Codex pattern
		const selection = ref( props.modelValue );
		const inputValue = ref( initialMatch ? initialMatch.label : '' );

		// Filter options based on type input
		const menuItems = computed( () => {
			if ( !inputValue.value ) {
				return countryOptions.value;
			}
			const query = inputValue.value.toLowerCase();
			return countryOptions.value.filter( ( item ) => item.label.toLowerCase().includes( query )
			);
		} );

		// Emit selection changes up to parent
		watch( selection, ( newCountry ) => {
			if ( !newCountry ) {
				emit( 'update:modelValue', null );
				return;
			}
			emit( 'update:modelValue', newCountry );
			emit( 'country-change', newCountry );
		} );

		return {
			selection,
			inputValue,
			menuItems
		};
	}
} );
</script>
