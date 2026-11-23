<template>
	<!-- The submit button of the forms that charge the donation themselves (card, SEPA).
	Shows the amount charged, so the fee is included when the donor opted in. -->
	<cdx-button
		action="progressive"
		weight="primary"
		class="combo-wiki__button-submit combo-wiki__donate-button"
		:disabled="submitting"
		@click="$emit( 'click' )"
	>
		<template v-if="submitting">
			...
		</template>
		<!-- The legacy forms' padlock next to the label, see comboWiki.less -->
		<span v-else class="combo-wiki__donate-button-content">
			<span class="combo-wiki__donate-button-lock" aria-hidden="true"></span>
			<span>{{ label }}</span>
		</span>
	</cdx-button>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'DonateButton',

	components: {
		CdxButton
	},

	// Strings rather than the donation, so the button only re-renders when its label changes
	props: {
		// The amount charged, formatted for the donor's locale, e.g. $20.80.
		// It includes the fee when the donor opted in, see chargedDonation in PaymentForm.
		formattedAmount: {
			type: String,
			default: ''
		},
		currency: {
			type: String,
			default: ''
		},
		submitting: {
			type: Boolean,
			default: false
		}
	},

	emits: [ 'click' ],

	computed: {
		label() {
			if ( !this.formattedAmount ) {
				return this.$i18n( 'donate_interface-submit-button' ).text();
			}
			return this.$i18n( 'combowiki-donate-amount', this.formattedAmount, this.currency ).text();
		}
	}
} );
</script>
