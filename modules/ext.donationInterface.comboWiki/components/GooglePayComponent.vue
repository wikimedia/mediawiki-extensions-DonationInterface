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
const { getGateway } = require( '../api.js' );

/**
 * For each gateway: its client config from Special:Donate, and how Google tokenizes the
 * card for it. The donate API sends the token to the gateway as payment_token.
 */
const GATEWAYS = {
	gravy: {
		configKey: 'gravyConfiguration',
		getTokenizationParameters: ( config ) => ( {
			gateway: 'gr4vy',
			gatewayMerchantId: config.gravyGooglePayMerchantId
		} ),
		// Gravy's Google Pay API requires the card's last digits and networ
		getExtraData: ( paymentMethodInfo ) => ( {
			card_suffix: paymentMethodInfo.cardDetails,
			card_scheme: paymentMethodInfo.cardNetwork
		} )
	},
	adyen: {
		configKey: 'adyenConfiguration',
		getTokenizationParameters: ( config ) => ( {
			gateway: 'adyen',
			// Called gatewayMerchantId, but it is our Adyen merchant account name
			gatewayMerchantId: config.merchantAccountName
		} ),
		// Adyen's submethod for the card, as on Special:AdyenCheckoutGateway
		getExtraData: ( paymentMethodInfo ) => {
			const network = paymentMethodInfo.cardNetwork.toLowerCase();
			return { payment_submethod: network === 'mastercard' ? 'mc' : network };
		}
	}
};

module.exports = exports = defineComponent( {
	name: 'GooglePayComponent',
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
		},
		paymentMethod: {
			type: String,
			required: true
		}
	},
	emits: [ 'submit', 'error' ],
	setup( props ) {
		// The gateway in paymentMethodMap, which the donate API posts to (see api.js).
		const gateway = GATEWAYS[ getGateway( props.paymentMethod ) ];
		const config = ref( '' );
		return {
			gateway,
			config
		};
	},
	methods: {
		getClient() {
			// Google posts the requests to its iframe, so they must not contain Vue proxies
			return getGooglePaymentsClient( toRaw( this.config ) );
		},
		async displayGooglePayButton() {
			// PaymentMethodForm only shows Google Pay once this check passed, so this is
			// normally answered from cache. If it fails now, tell the donor rather than
			// leaving them with a selected method and no button.
			if ( !await isGooglePayAvailable( toRaw( this.config ) ) ) {
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
			const config = toRaw( this.config );
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
			const tokenizationSpecification = {
				type: 'PAYMENT_GATEWAY',
				parameters: this.gateway.getTokenizationParameters( config )
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
						payment_token: paymentToken
					}, this.gateway.getExtraData( paymentMethodInfo ), paymentData );

					this.$emit( 'submit', donationParameters, null, this.handleFailedPaymentResult );
				} )
				.catch( ( error )  => {
					// would be google errors here
					console.log( error );
				} );
		}
	},
	created() {
		this.config = this.params[ this.gateway.configKey ];
	},
	mounted() {
		this.displayGooglePayButton();
	}
} );
</script>
