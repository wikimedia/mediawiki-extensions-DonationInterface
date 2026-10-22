<template>
	<div
		id="googlepay-container"
		class="payment-method-form"
		data-autoscroll
	></div>
</template>

<script>
const { defineComponent, toRaw, ref } = require( 'vue' );
const { getGooglePaymentsClient, isGooglePayAvailable } = require( '../walletAvailability.js' );

module.exports = exports = defineComponent( {
	name: 'GravyGoogleForm',
	components: {},
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
	setup() {
		const gravyConfig = ref( '' );
		return {
			gravyConfig
		};
	},
	methods: {
		getClient() {
			// Google posts the requests to its iframe, so they must not contain Vue proxies
			return getGooglePaymentsClient( toRaw( this.gravyConfig ) );
		},
		async displayGooglePayButton() {
			// PaymentMethodForm only shows Google Pay once this check passed, so this is
			// normally answered from cache. If it fails now, tell the donor rather than
			// leaving them with a selected method and no button.
			if ( !await isGooglePayAvailable( toRaw( this.gravyConfig ) ) ) {
				this.emitUnavailable();
				return;
			}
			// The donor may have switched to another method while the check ran, so
			// there is nowhere to draw the button. Not a reason to stop offering Google Pay.
			const container = document.getElementById( 'googlepay-container' );
			if ( !container ) {
				return;
			}
			try {
				const button = this.getClient().createButton( {
					onClick: mw.util.debounce( () => {
						this.onButtonClicked();
					}, 100 ),
					allowedPaymentMethods: [ 'CARD', 'TOKENIZED_CARD' ],
					buttonType: 'donate'
				} );
				container.appendChild( button );
			} catch ( err ) {
				mw.log.error( 'combowiki googlepay button failed', err );
				this.emitUnavailable();
			}
		},
		/**
		 * Tells the donor this browser can't pay with Google Pay.
		 */
		emitUnavailable() {
			this.$emit( 'error', {
				type: 'validation',
				messages: [ mw.message(
					'donate_interface-error-msg-google_pay_unsupported',
					this.params.DonationInterfaceOtherWaysURL
				).plain() ]
			} );
		},
		handleFailedPaymentResult( err ) {
			this.$emit( 'error', [
				mw.message(
					`Google Pay failure: ${ err }`,
					mw.config.get( 'DonationInterfaceOtherWaysURL' )
				).plain()
			] );
		},
		getPaymentRequest() {
			const config = toRaw( this.gravyConfig );
			const paymentRequest = {
				apiVersion: 2,
				apiVersionMinor: 0
			};
			const cardPaymentMethod = {
				type: 'CARD',
				parameters: {
					allowedCardNetworks: config.googleAllowedNetworks,
					allowedAuthMethods: [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ],
					billingAddressRequired: true,
					billingAddressParameters: {
						format: 'FULL'
					}
				}
			};
			const gravyGooglePayMerchantId = config.gravyGooglePayMerchantId;
			const tokenizationSpecification = {
				type: 'PAYMENT_GATEWAY',
				parameters: {
					gateway: 'gr4vy',
					gatewayMerchantId: gravyGooglePayMerchantId
				}
			};
			cardPaymentMethod.tokenizationSpecification = tokenizationSpecification;
			paymentRequest.allowedPaymentMethods = [ cardPaymentMethod ];
			paymentRequest.transactionInfo = {
				totalPriceStatus: 'FINAL',
				totalPrice: Number( this.donation.amount ).toFixed( 2 ),
				currencyCode: this.donation.currency,
				countryCode: this.donation.country
			};
			paymentRequest.merchantInfo = {
				merchantName: 'WikimediaFoundation',
				merchantId: config.googleMerchantId
			};
			paymentRequest.emailRequired = true;
			return paymentRequest;
		},
		onButtonClicked() {
			// Before the payment sheet opens, so the donor can fix the form first
			if ( this.donorDetails && !this.donorDetails.validate() ) {
				return;
			}
			this.getClient()
				.loadPaymentData( this.getPaymentRequest() )
				.then( ( paymentData ) => {
					const paymentMethodData = paymentData.paymentMethodData;
					const paymentMethodInfo = paymentMethodData.info;
					const paymentToken = paymentMethodData.tokenizationData.token;
					const donorInfo = paymentMethodInfo.billingAddress;

					const donationParameters = Object.assign( {}, {
						full_name: donorInfo.name,
						email: paymentData.email,
						postal_code: donorInfo.postalCode,
						state_province: donorInfo.administrativeArea,
						city: donorInfo.locality,
						street_address: donorInfo.address1,
						payment_token: paymentToken,
						card_suffix: paymentMethodInfo.cardDetails,
						card_scheme: paymentMethodInfo.cardNetwork
					}, paymentData );

					this.$emit( 'submit', donationParameters, null, this.handleFailedPaymentResult );
				} )
				.catch( ( error )  => {
					// would be google errors here
					console.log( error );
				} );
		}
	},
	created() {
		this.gravyConfig = this.params.gravyConfiguration;
	},
	mounted() {
		this.displayGooglePayButton();
	}
} );
</script>
