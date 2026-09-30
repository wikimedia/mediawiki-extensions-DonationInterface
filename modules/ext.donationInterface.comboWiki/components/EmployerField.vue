<template>
	<div class="combo-wiki__employer">
		<cdx-field :is-required="false">
			<cdx-label input-id="combo-employer-name">
				{{ employerLabel }}
			</cdx-label>
			<cdx-lookup
				id="combo-employer-name"
				v-model:selected="selectedEmployerId"
				:menu-items="employers"
				@input="onInput"
				@update:selected="onSelect"
			>
			</cdx-lookup>
			<template #help-text>
				{{ employerExplain }}
			</template>
		</cdx-field>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxLookup, CdxField, CdxLabel } = require( '@wikimedia/codex' );
const { apiPost } = require( '../api.js' );

module.exports = defineComponent( {
	name: 'EmployerField',
	components: {
		'cdx-field': CdxField,
		'cdx-label': CdxLabel,
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
		}
	},
	emits: [ 'update:employer', 'update:employer-id' ],
	setup( props ) {
		const employers = ref( [] ),
			selectedEmployerId = ref( props.employerId ),
			employersById = {},
			autocompleteCache = {},
			previouslySelectedEmployersByName = {},
			selectedEmployerName = '';

		if ( props.employer && props.employerId ) {
			employers.value.push( {
				label: props.employer,
				value: props.employerId
			} );
		}
		return {
			autocompleteCache,
			employers,
			employersById,
			previouslySelectedEmployersByName,
			selectedEmployerId,
			selectedEmployerName
		};
	},
	computed: {
		employerLabel() {
			return this.$i18n( 'donate_interface-donor-employer' ).text();
		},
		employerExplain() {
			return this.$i18n( 'donate_interface-donor-employer-explain' ).text();
		}
	},
	methods: {
		onInput( value ) {
			const apiParameters = {
					action: 'employerSearch',
					employer: value,
					format: 'json'
				},
				cached = this.autocompleteCache[ value ];

			if ( cached ) {
				this.employers = cached;
			} else {
				apiPost( apiParameters ).then( ( data ) => {
					// check if the api sent back any errors and if so jump out here
					if ( data.error ) {
						this.employers = [];
					} else {
						// transform result to suit autocomplete format
						const result = data.result.map( ( item ) => ( {
								label: item.name,
								value: item.id
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
				} );
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
			this.$emit( 'update:employer', employerName );
			this.$emit( 'update:employer-id', employerId );
		}
	}
} );

</script>
