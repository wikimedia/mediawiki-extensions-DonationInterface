<template>
	<div class="combo-wiki__sepa payment-method-form">
		<div id="adyen-sepa-secure-fields-container"></div>
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
				@click="submit"
			></donate-button>
		</method-submit>
	</div>
</template>

<script>

const { defineComponent, toRaw } = require( 'vue' );
const { getAdyenCheckout, handleAdyenError } = require( '../utils.js' );
const MethodSubmit = require( './MethodSubmit.vue' );
const DonateButton = require( './DonateButton.vue' );
const { getFormattedAmount } = require( '../amountHelper.js' );

module.exports = exports = defineComponent( {
	name: 'AdyenSEPA',

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
			checkout: null,
			sepa: null,
			fieldsReady: false,
			formValid: false,
			wmfToken: this.params.wmf_token
		};
	},

	computed: {
		formattedAmount() {
			return getFormattedAmount( this.donation.amount, this.params.language, this.donation.currency, this.donation.country );
		}
	},

	methods: {
		async setupAdyenSepa() {
			try {
				this.checkout = await getAdyenCheckout( this.donation.country );
				this.sepa = new window.AdyenWeb.SepaDirectDebit( this.checkout, {
					showPayButton: false,
					placeholders: {
						ownerName: mw.msg( 'donate_interface-bt-account_holder' ),
						ibanNumber: 'IBAN'
					},
					onChange: ( state ) => {
						this.formValid = state.isValid;
					},
					onSubmit: ( state, component ) => {
						this.handleSubmit( state, component );
					},
					onError: ( error ) => {
						this.handleError( error );
					}
				} );
				this.sepa.mount( '#adyen-sepa-secure-fields-container' );
				this.fieldsReady = true;
			} catch ( error ) {
				this.handleError( error );
			}
		},
		submit() {
			if ( !this.donorDetails || ( this.donorDetails && !this.donorDetails.validate() ) ) {
				return;
			}
			if ( !this.sepa || !this.formValid ) {
				return;
			}
			this.sepa.submit();
		},
		handleError( error ) {
			handleAdyenError(
				error,
				( shownError ) => this.$emit( 'error', shownError ),
				this.params.DonationInterfaceOtherWaysURL
			);
		},
		handleSubmit( state ) {
			const extraData = {
				wmf_token: this.wmfToken,
				full_name: state.data.paymentMethod.ownerName,
				// The International Bank Account Number
				iban: state.data.paymentMethod.iban,
				payment_submethod: 'sepadirectdebit'
			};
			this.$emit( 'submit', extraData );
		}
	},

	async mounted() {
		const config = toRaw( this.params.adyenConfiguration );

		if ( !config ) {
			this.$emit( 'error', 'adyen-configuration-missing' );
			return;
		}
		await this.setupAdyenSepa();
	},

	beforeUnmount() {
		if ( this.sepa ) {
			this.sepa.unmount();
			this.sepa = null;
		}

		this.checkout = null;
	}
} );
</script>
