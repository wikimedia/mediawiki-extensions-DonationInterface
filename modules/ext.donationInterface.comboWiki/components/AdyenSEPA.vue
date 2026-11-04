<template>
	<div class="combo-wiki__sepa payment-method-form">
		<div id="adyen-sepa-secure-fields-container"></div>
		<p v-if="donation.frequency === 'once'">
			{{ $i18n( 'donate_interface-sepa-mandate' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'monthly'">
			{{ $i18n( 'donate_interface-sepa-mandate-monthly' ).text() }}
		</p>
		<p v-else-if="donation.frequency === 'annual'">
			{{ $i18n( 'donate_interface-sepa-mandate-yearly' ).text() }}
		</p>

		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>

const { defineComponent, toRaw } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const { getAdyenCheckout, handleAdyenError } = require( '../utils.js' );

module.exports = exports = defineComponent( {
	name: 'AdyenSEPA',

	components: {
		CdxButton
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
