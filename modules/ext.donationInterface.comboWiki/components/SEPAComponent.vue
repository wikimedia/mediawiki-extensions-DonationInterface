<template>
	<div class="combo-wiki__sepa payment-method-form">
		<p v-if="donation.frequency === 'once'">
			{{ $i18n( 'donate_interface-sepa-mandate' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'month'">
			{{ $i18n( 'donate_interface-sepa-mandate-monthly' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'year'">
			{{ $i18n( 'donate_interface-sepa-mandate-yearly' ).text() }}
		</p>

		<method-submit>
			<donate-button
				:formatted-amount="formattedAmount"
				:currency="donation.currency"
				:submitting="isSubmitting"
				@click="submit"
			></donate-button>
		</method-submit>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const MethodSubmit = require( './MethodSubmit.vue' );
const DonateButton = require( './DonateButton.vue' );
const { getFormattedAmount } = require( '../amountHelper.js' );

module.exports = exports = defineComponent( {
	name: 'SEPAComponent',

	components: {
		'method-submit': MethodSubmit,
		'donate-button': DonateButton
	},

	inject: [ 'params' ],

	props: {
		donation: {
			type: Object,
			required: true
		},
		// The details form above this one, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},

	emits: [ 'submit', 'error' ],

	data() {
		return {
			isSubmitting: false
		};
	},

	computed: {
		formattedAmount() {
			return getFormattedAmount( this.donation.amount, this.params.language, this.donation.currency, this.donation.country );
		}
	},

	methods: {
		handleSubmitFailure() {
			this.isSubmitting = false;
		},
		submit() {
			if ( this.isSubmitting || ( this.donorDetails && !this.donorDetails.validate() ) ) {
				return;
			}

			this.isSubmitting = true;
			this.$emit(
				'submit',
				{},
				null,
				this.handleSubmitFailure
			);
		}
	}
} );
</script>
