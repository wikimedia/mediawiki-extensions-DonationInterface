<template>
	<div class="combo-wiki__employer">
		<cdx-field :optional="!isRequired">
			<cdx-lookup
				id="combo-employer-name"
				v-model:selected="selectedEmployerId"
				:menu-items="employers"
				data-autoscroll
				@input="onInput"
				@update:selected="onSelect"
			>
			</cdx-lookup>
			<template #help-text>
				{{ employerExplain }}
			</template>
			<template #label>
				{{ employerLabel }}
			</template>
		</cdx-field>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxLookup, CdxField } = require( '@wikimedia/codex' );
const { apiPost } = require( '../api.js' );

module.exports = defineComponent( {
	name: 'EmployerField',
	components: {
		'cdx-field': CdxField,
		'cdx-lookup': CdxLookup
	},
	props: {
		employer: {
			type: String,
			default: ''
		},
		employerId: {
			type: Number,
			default: 0
		},
		isRequired: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'update:employer', 'update:employer-id' ],
	setup( props ) {
		const employers = ref( [] ),
			selectedEmployerId = ref( props.employerId ),
			employersById = {},
			autocompleteCache = {},
			previouslySelectedEmployersByName = {},
			selectedEmployerName = '',
			// assigned per instance in created(), where this.searchEmployers exists
			debouncedSearch = null;

		if ( props.employer && props.employerId ) {
			employers.value.push( {
				label: props.employer,
				value: props.employerId
			} );
		}
		return {
			autocompleteCache,
			debouncedSearch,
			employers,
			employersById,
			previouslySelectedEmployersByName,
			selectedEmployerId,
			selectedEmployerName
		};
	},
	computed: {
		employerLabel() {
			// Update to remove (optional) since it is provided by the codex framework
			return this.$i18n( 'donate_interface-donor-employer-plain' ).text();
		},
		employerExplain() {
			return this.$i18n( 'donate_interface-donor-employer-explain' ).text();
		}
	},
	methods: {
		resetEmployerFieldValues() {
			this.employers = [];
			this.updateEmployerIdAndName( 0, '' );
		},
		searchEmployers( value ) {
			const apiParameters = {
				action: 'employerSearch',
				employer: value,
				format: 'json'
			};
			apiPost( apiParameters )
				.then( ( data ) => {
					// check if the api sent back any errors and if so jump out here
					if ( data.error ) {
						this.resetEmployerFieldValues();
					} else {
						// transform result to suit autocomplete format; ids come back as
						// strings from the CSV but the employerId prop is a Number
						const result = data.result.map( ( item ) => ( {
								label: item.name,
								value: Number( item.id )
							} ) ),
							// trim results
							output = result.slice( 0, 10 );
						// Store the values in our lookup table so we can get the name on select
						data.result.forEach( ( item ) => {
							this.employersById[ item.id ] = item.name;
						} );
						// cache result
						this.autocompleteCache[ value ] = output;
						this.employers = output;
					}
				} )
				// mw.Api rejects on API errors, so the error should be caught here
				.catch( () => {
					this.resetEmployerFieldValues();
				} );
		},
		onInput( value ) {
			// if null or empty string, reset the employer and field skip the
			// API call that throws a mw.Api error: missingparam
			if ( !value || !value.trim() ) {
				this.resetEmployerFieldValues();
				return;
			}
			const cached = this.autocompleteCache[ value ];

			if ( cached ) {
				this.employers = cached;
			} else {
				this.debouncedSearch( value );
			}
			if ( this.previouslySelectedEmployersByName[ value ] ) {
				// Donor has restored the input box to match a previously selected value
				const employerId = this.previouslySelectedEmployersByName[ value ];
				this.updateEmployerIdAndName( employerId, value );
			}
			if ( this.selectedEmployerName && !this.selectedEmployerName.startsWith( value ) ) {
				// Donor is typing something new after selecting something. Reset the name
				// and ID so that we don't send a value unless they select something new.
				this.updateEmployerIdAndName( 0, '' );
			}
		},
		onSelect( employerId ) {
			const employerName = this.employersById[ employerId ];
			this.previouslySelectedEmployersByName[ employerName ] = employerId;
			this.updateEmployerIdAndName( employerId, employerName );
		},
		updateEmployerIdAndName( employerId, employerName ) {
			this.selectedEmployerName = employerName;
			this.selectedEmployerId = employerId;
			this.$emit( 'update:employer', employerName );
			this.$emit( 'update:employer-id', employerId );
		}
	},
	created() {
		this.debouncedSearch = mw.util.debounce( this.searchEmployers, 300 );
	}
} );

</script>
