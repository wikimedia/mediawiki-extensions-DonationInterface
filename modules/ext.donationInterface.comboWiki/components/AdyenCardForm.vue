<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div id="combo-adyen-card"></div>
		<div id="combo-adyen-card-action-container"></div>
		<cdx-button
			action="progressive"
			weight="primary"
			class="combo-wiki__button-submit"
			:disabled="!formValid"
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
	name: 'AdyenCardForm',

	components: {
		'cdx-button': CdxButton
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

	emits: [ 'presubmit', 'submit', 'error' ],

	data() {
		return {
			checkout: null,
			card: null,
			fieldsReady: false,
			formValid: false,
			wmfToken: this.params.wmf_token
		};
	},

	methods: {
		async setupAdyen() {
			try {
				// Shared with the other Adyen components on the page, so the callbacks
				// are set on the card rather than on the checkout
				this.checkout = await getAdyenCheckout( this.donation.country );
				const placeholders = {
					cardNumber: mw.msg( 'donate_interface-credit-card-number' ),
					expiryDate: mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
					securityCodeThreeDigits: mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
					securityCodeFourDigits: mw.msg( 'donate_interface-cvv-placeholder-4-digits' )
				};
				this.card = new window.AdyenWeb.Card( this.checkout, {
					showBrandsUnderCardNumber: false,
					showPayButton: false,
					placeholders: placeholders,
					styles: {
						base: {
							fontSize: '17.5px',
							fontSmoothing: 'antialiased',
							fontFamily: 'sans-serif',
							lineHeight: '1.375;',
							fontWeight: '400'
						},
						placeholder: {
							color: '#72777d',
							fontWeight: '400'
						}
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

				this.card.mount( '#combo-adyen-card' );

				this.fieldsReady = true;
			} catch ( error ) {
				this.handleError( error );
			}
		},

		submit() {
			if ( !this.card || !this.formValid ) {
				return;
			}
			if ( this.donorDetails && !this.donorDetails.validate() ) {
				return;
			}
			this.card.submit();
		},

		handleError( error ) {
			handleAdyenError(
				error,
				( shownError ) => this.$emit( 'error', shownError ),
				this.params.DonationInterfaceOtherWaysURL
			);
		},

		handleSubmitSuccess( result ) {
			if ( !this.checkout ) {
				this.handleError( 'missing-adyen-config' );
				return;
			}
			const response = result.result;

			if ( response && response.formData && Object.keys( response.formData ).length > 0 ) {
				this.checkout.createFromAction( {
					paymentMethodType: 'scheme',
					url: response.redirect,
					data: response.formData,
					method: 'POST',
					type: 'redirect'
				} ).mount( '#combo-adyen-card-action-container' );
			}
		},

		handleSubmit( state, component ) {
			if ( !state.isValid ) {
				return;
			}

			const paymentMethod = state.data ? state.data.paymentMethod : null;

			if ( !paymentMethod ) {
				this.handleError( 'adyen-payment-method-missing' );
				return;
			}

			const extraData = {
				wmf_token: this.wmfToken,
				encrypted_card_number: paymentMethod.encryptedCardNumber,
				encrypted_expiry_month: paymentMethod.encryptedExpiryMonth,
				encrypted_expiry_year: paymentMethod.encryptedExpiryYear,
				encrypted_security_code: paymentMethod.encryptedSecurityCode,

				payment_submethod: this.mapAdyenSubmethod(
					paymentMethod.brand ||
						( component.state && component.state.brand ) ||
						''
				),

				color_depth: state.data.browserInfo ?
					state.data.browserInfo.colorDepth :
					screen.colorDepth || 24,

				java_enabled: state.data.browserInfo ?
					state.data.browserInfo.javaEnabled :
					false,

				screen_height: state.data.browserInfo ?
					state.data.browserInfo.screenHeight :
					screen.height || 0,

				screen_width: state.data.browserInfo ?
					state.data.browserInfo.screenWidth :
					screen.width || 0,

				time_zone_offset: this.getTimeZoneOffset(
					state.data.browserInfo
				)
			};

			this.$emit( 'submit', extraData, this.handleSubmitSuccess );
		},

		getTimeZoneOffset( browserInfo ) {
			if (
				browserInfo &&
				typeof browserInfo.timeZoneOffset !== 'undefined' &&
				!isNaN( Math.floor( browserInfo.timeZoneOffset ) )
			) {
				return Math.floor( browserInfo.timeZoneOffset );
			}

			return new Date().getTimezoneOffset();
		},

		mapAdyenSubmethod( adyenBrandCode ) {
			switch ( adyenBrandCode.toLowerCase() ) {
			case 'bijcard':
				return 'bij';

			case 'cartebancaire':
				return 'cb';

			case 'mc-debit':
				return 'mc';

			case 'visadankort':
				return 'visa';

			case 'visadebit':
			case 'vpay':
				return 'visa-debit';

			case 'visabeneficial':
				return 'visa-beneficial';

			case 'visaelectron':
				return 'visa-electron';

			case 'mastercard':
				return 'mc';

			default:
				return adyenBrandCode;
			}
		}
	},

	async mounted() {
		const config = toRaw( this.params.adyenConfiguration );

		if ( !config ) {
			this.$emit( 'error', 'adyen-configuration-missing' );
			return;
		}

		await this.setupAdyen();
	},

	beforeUnmount() {
		if ( this.card ) {
			this.card.unmount();
			this.card = null;
		}

		this.checkout = null;
	}
} );
</script>
