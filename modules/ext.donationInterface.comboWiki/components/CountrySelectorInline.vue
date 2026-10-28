<template>
	<div
		ref="root"
		class="country-selector-inline"
		@keydown.capture="onKeydownCapture"
	>
		<template v-if="!isEditing">
			<span class="country-selector-inline__value">{{ displayLabel }}</span>
			<button
				ref="changeButton"
				class="country-selector-inline__change"
				type="button"
				:aria-label="changeButtonLabel"
				@click="startEditing"
			>
				{{ $i18n( 'combowiki-country-change' ).text() }}
			</button>
		</template>
		<cdx-lookup
			v-else
			v-model:selected="selection"
			v-model:input-value="inputValue"
			class="country-selector-inline__lookup"
			:menu-items="menuItems"
			:clearable="true"
			:placeholder="$i18n( 'combowiki-country-search-placeholder' ).text()"
			:aria-label="$i18n( 'combowiki-country-label' ).text()"
			data-1p-ignore
			data-lpignore="true"
			data-bwignore
			data-form-type="other"
			@update:selected="onSelect"
			@blur="onLookupBlur"
		>
			<template #no-results>
				{{ $i18n( 'combowiki-country-no-results' ).text() }}
			</template>
		</cdx-lookup>
	</div>
</template>

<script>
const { defineComponent, ref, computed, nextTick } = require( 'vue' );
const { CdxLookup } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'CountrySelectorInline',

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
		const root = ref( null );
		const changeButton = ref( null );

		const isEditing = ref( false );
		const selection = ref( null );
		const inputValue = ref( '' );

		// Sort by country name using js Intl.Collator based on donor language
		const collator = new Intl.Collator( mw.config.get( 'wgUserLanguage' ), { sensitivity: 'base' } );

		// Format options list ({ value: 'US', label: 'United States (USD)' })
		const countryOptions = computed( () => Object.entries( props.countries )
			.sort( ( [ , a ], [ , b ] ) => collator.compare( a.label, b.label ) )
			.map( ( [ code, config ] ) => ( {
				value: code,
				label: `${ config.label } (${ config.currency })`
			} ) )
		);

		// Filter options based on type input
		const menuItems = computed( () => {
			if ( !inputValue.value ) {
				return countryOptions.value;
			}
			const query = inputValue.value.toLowerCase();
			return countryOptions.value.filter( ( item ) => item.label.toLowerCase().includes( query )
			);
		} );

		const displayLabel = computed( () => {
			const match = countryOptions.value.find( ( item ) => item.value === props.modelValue );
			return match ? match.label : mw.msg( 'combowiki-country-placeholder' );
		} );

		// Set what screen readers will read
		const changeButtonLabel = computed(
			() => mw.msg( 'combowiki-country-change-label', displayLabel.value )
		);

		function focusInput() {
			const input = root.value && root.value.querySelector( 'input' );
			if ( input ) {
				input.focus();
			}
		}

		function startEditing() {
			// Not prefilling to show the full list when open
			selection.value = null;
			inputValue.value = '';
			isEditing.value = true;
			nextTick( focusInput );
		}

		function stopEditing( returnFocus ) {
			if ( !isEditing.value ) {
				return;
			}
			isEditing.value = false;
			nextTick( () => {
				const active = document.activeElement;
				const focusLost = !active || active === document.body ||
					( root.value && root.value.contains( active ) );
				if ( returnFocus && focusLost && changeButton.value ) {
					changeButton.value.focus();
				}
			} );
		}

		function onSelect( newCountry ) {
			// Lookup emits null while the donor is typing; ignore that
			if ( !newCountry ) {
				return;
			}
			if ( newCountry !== props.modelValue ) {
				emit( 'update:modelValue', newCountry );
				emit( 'country-change', newCountry );
			}
			stopEditing( true );
		}

		// Keeps the current country when clicking outside without choosing any
		function onLookupBlur( event ) {
			if ( event.relatedTarget && root.value && root.value.contains( event.relatedTarget ) ) {
				return;
			}
			stopEditing( false );
		}

		// Pressing Escape closes the list. A second Escape cancels editing
		function onKeydownCapture( event ) {
			if ( !isEditing.value || event.key !== 'Escape' ) {
				return;
			}
			const input = root.value.querySelector( 'input' );
			if ( input && input.getAttribute( 'aria-expanded' ) !== 'true' ) {
				event.preventDefault();
				stopEditing( true );
			}
		}

		return {
			root,
			changeButton,
			isEditing,
			selection,
			inputValue,
			menuItems,
			displayLabel,
			changeButtonLabel,
			startEditing,
			onSelect,
			onLookupBlur,
			onKeydownCapture
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';
.country-selector-inline {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: @spacing-25;
	box-sizing: border-box;
	min-height: @min-size-interactive-pointer;

	&__change {
		// Reset native button styles so it reads as inline text
		padding: 0;
		border: 0;
		background: none;
		font: inherit;
		color: @color-progressive;
		cursor: pointer;

		&:hover {
			color: @color-progressive--hover;
			text-decoration: underline;
		}

		&:focus-visible {
			outline: @border-width-thick @border-style-base @border-color-progressive--focus;
			outline-offset: 2px;
			border-radius: @border-radius-base;
		}
	}

	&__lookup {
		flex: 1 1 100%;
	}
}
</style>
