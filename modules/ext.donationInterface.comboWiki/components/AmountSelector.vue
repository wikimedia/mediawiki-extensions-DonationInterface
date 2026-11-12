<template>
	<div class="fieldset">
		<!-- Button Stack -->
		<cdx-field :status="status" :messages="messages">
			<!-- Label -->
			<div class="fieldset__label">
				<p class="text text--base">
					<strong>{{ amountHeading }}</strong>
				</p>
				<!-- Country Component -->
				<country-selector
					:model-value="country"
					:countries="countries"
					@country-change="$emit( 'update:country', $event )"
				></country-selector>
			</div>
			<div class="fiedlset__button-grid">
				<!-- Fixed Amounts -->
				<cdx-toggle-button
					v-for="amount in presetAmounts"
					:key="amount"
					:model-value="!otherAmount && Number( modelValue ) === amount"
					@update:model-value="selectAmount( amount )"
				>
					{{ formattedAmount( amount ) }}
				</cdx-toggle-button>
				<!-- Custom Amount -->
				<cdx-text-input
					:model-value="otherAmount"
					input-type="number"
					:placeholder="$i18n( 'donate_interface-other-amount' ).text()"
					@update:model-value="setOtherAmount"
				>
				</cdx-text-input>
			</div>
			<!-- Pay the fee, shown once there is an amount to base the fee on -->
			<cdx-checkbox
				v-if="canCoverFee"
				class="combo-wiki__amount-cover-fees"
				:model-value="payFee"
				@update:model-value="$emit( 'update:payFee', $event )"
			>
				{{ $i18n( 'combowiki-cover-fees', formattedAmount( suggestedFee ) ).text() }}
			</cdx-checkbox>
		</cdx-field>
	</div>
</template>

<script>
const { defineComponent, ref, computed, inject } = require( 'vue' );
const { CdxToggleButton, CdxTextInput, CdxCheckbox, CdxField } = require( '@wikimedia/codex' );
const { getAmountHeading } = require( '../frequencyOptions.js' );
const CountrySelector = require( './CountrySelectorInline.vue' );
const { getSuggestedFee, canCoverFee, getFormattedAmount, getPresetAmounts } = require( '../amountHelper.js' );

module.exports = exports = defineComponent( {
	name: 'AmountSelector',
	components: {
		'cdx-toggle-button': CdxToggleButton,
		'cdx-text-input': CdxTextInput,
		'cdx-checkbox': CdxCheckbox,
		'cdx-field': CdxField,
		'country-selector': CountrySelector
	},
	inject: {
		params: { from: 'params' }
	},
	props: {
		// A number from the presets, or the string typed into the other amount field
		modelValue: {
			type: [ Number, String ],
			default: null
		},
		frequency: {
			type: String,
			default: 'once'
		},
		country: {
			type: String,
			default: ''
		},
		countries: {
			type: Object,
			default: () => ( {} )
		},
		currency: {
			type: String,
			default: ''
		},
		payFee: {
			type: Boolean,
			default: false
		},
		status: {
			type: String,
			default: 'default'
		},
		messages: {
			type: Object,
			default: () => ( {} )
		}
	},
	emits: [ 'update:modelValue', 'update:country', 'update:payFee', 'onChange' ],
	// What is charged, including the fee, is worked out by PaymentForm from the same
	// helpers, so it is up to date as soon as the donation changes
	setup: ( props ) => {
		const params = inject( 'params' );
		return {
			suggestedFee: computed( () => getSuggestedFee( props.modelValue, props.currency, params ) ),
			canCoverFee: computed( () => canCoverFee( props.modelValue, props.currency, params ) ),
			otherAmount: ref( '' )
		};
	},
	computed: {
		amountHeading() {
			return getAmountHeading( this.frequency );
		},
		presetAmounts() {
			const rates = this.params.wgDonationInterfaceCurrencyRates || {};
			const currency = this.currency;
			return getPresetAmounts( currency, rates );
		},
		formattedAmount() {
			return ( amount ) => getFormattedAmount( amount, this.params.language, this.currency, this.country );
		}
	},
	methods: {
		selectAmount( value ) {
			this.otherAmount = '';
			this.$emit( 'update:modelValue', value );
			this.$emit( 'onChange', value );
		},
		// Sets amount from the URL
		setAmount() {
			const amount = Number( this.params.amount );
			// Make sure it's actually a number
			if ( !isFinite( amount ) || amount <= 0 ) {
				return;
			}
			// Set the amounts
			if ( this.presetAmounts.includes( amount ) ) {
				this.selectAmount( amount );
			} else {
				this.setOtherAmount( String( amount ).trim() );
			}
		},
		setOtherAmount( value ) {
			this.otherAmount = value;
			this.$emit( 'update:modelValue', value );
			this.$emit( 'onChange', value );
		}
	},
	created() {
		this.setAmount();
	}
} );
</script>
