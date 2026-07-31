<template>
	<div id="googlepay-container"></div>
</template>
<script>
/* global SecureFields */
const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );
const api = require( '../api.js' );
let googlePaymentClient = null;
module.exports = exports = defineComponent({
	name: 'GravyGoogleForm',
	components: {
		'cdx-button': CdxButton,
		'cdx-text-input': CdxTextInput
	},
	props: {
		donation: {
			type: Object,
			required: true
		}
	},
	emits: [ 'submit', 'error' ],
	created() {
		this.gravyConfig = mw.config.get( 'gravyConfiguration' );
	},
	mounted() {
		this.loadScript( this.gravyConfig.googleScript ).then( () => this.displayGooglePayButton() );
	},
	computed: {
	},
	methods: {
		loadScript( src ) {
			return new Promise( ( resolve , reject )  => {
				const node = document.createElement( 'script' );
				node.src = src;
				node.onload = resolve;
				node.onerror = reject;
				document.body.append( node );
			});
		},
		getClient() {
			if ( !googlePaymentClient ) {
				googlePaymentClient = new google.payments.api.PaymentsClient( { environment: this.gravyConfig.googleEnvironment } );
			}
			return googlePaymentClient;
		},
		getGoogleBaseRequest() {
			return {
				apiVersion: 2,
				apiVersionMinor: 0
			};
		},
		getGoogleBaseCardPaymentMethod() {
			const allowedCardNetworks = this.gravyConfig.googleAllowedNetworks;
			const allowedCardAuthMethods = [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ];
			return {
				type: 'CARD',
				parameters: {
					allowedCardNetworks: allowedCardNetworks,
					allowedAuthMethods: allowedCardAuthMethods,
					billingAddressRequired: true,
					billingAddressParameters: {
						format: 'FULL'
					}
				}
			};
		},
		displayGooglePayButton() {
			const googlePayClient = this.getClient();
			const request = this.getGoogleBaseRequest();
			const baseCardPaymentMethod = this.getGoogleBaseCardPaymentMethod();
			request.allowedPaymentMethods = [ baseCardPaymentMethod ];
			googlePayClient
				.isReadyToPay( request )
				.then( ( response ) => {
					if ( response && response.result ) {
						const button = googlePayClient.createButton( {
							onClick: mw.util.debounce( () => {
								this.onButtonClicked();
							}, 100 ),
							allowedPaymentMethods: [ 'CARD', 'TOKENIZED_CARD' ],
							buttonType: 'donate'
						} );
						document.getElementById( 'googlepay-container' ).appendChild( button );
					}
				} )
				.catch( ( err ) => {
					mw.donationInterface.forms.addDebugMessage( 'Google Pay failure: ' + err );
				} );
		},
		getPaymentRequest() {
			const paymentRequest = {
				apiVersion: 2,
				apiVersionMinor: 0
			};
			const cardPaymentMethod = {
				type: 'CARD',
				parameters: {
					allowedCardNetworks: this.gravyConfig.googleAllowedNetworks,
					allowedAuthMethods: [ 'PAN_ONLY', 'CRYPTOGRAM_3DS' ],
					billingAddressRequired: true,
					billingAddressParameters: {
						format: 'FULL'
					}
				}
			};
			const gravyGooglePayMerchantId = this.gravyConfig.gravyGooglePayMerchantId;
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
				merchantId: this.gravyConfig.googleMerchantId
			};
			paymentRequest.emailRequired = true;
			return paymentRequest;
		},
		onButtonClicked() {
			this.getClient()
				.loadPaymentData( this.getPaymentRequest() )
				.then( ( paymentData ) => {
					this.$emit( 'submit', paymentData, null, this.handleFailedPaymentResult
					);
				} )
				.catch( ( error )  => {
					// would be google errors here
					console.log(error);
				} );
		}
	}
});
</script>
