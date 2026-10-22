<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div id="combo-adyen-card" data-autoscroll></div>
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
/* global AdyenCheckout */

const { defineComponent } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'AdyenCardForm',

	components: {
		'cdx-button': CdxButton
	},

	inject: [ 'params' ],

	props: {
		// Unused, but PaymentMethodForm passes it to every payment method form,
		// so declaring it keeps it from falling through as a DOM attribute
		// eslint-disable-next-line vue/no-unused-properties
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
		loadScript( src ) {
			return new Promise( ( resolve, reject ) => {
				const node = document.createElement( 'script' );

				node.src = src;
				node.onload = resolve;
				node.onerror = reject;

				document.body.append( node );
			} );
		},

		async setupAdyen( config ) {
			try {
				const checkoutConfig = {
					clientKey: config.clientKey,
					environment: config.environment,
					locale: config.locale,
					paymentMethodsResponse: config.paymentMethodsResponse,

					onChange: ( state ) => {
						this.formValid = state.isValid;
					},

					onSubmit: ( state, component ) => {
						this.handleSubmit( state, component );
					},

					onError: ( error ) => {
						this.handleError( error );
					}
				};

				this.checkout = await AdyenCheckout( checkoutConfig );

				this.card = this.checkout.create( 'card', {
					showBrandsUnderCardNumber: false
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
			// Ignore blank errors, matching the existing Adyen integration.
			if ( error && typeof error.error === 'string' && error.error === '' ) {
				return;
			}

			this.$emit( 'error', error );
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

			this.$emit( 'submit', extraData );
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
		const config = this.params.adyenConfiguration;

		if ( !config ) {
			this.$emit( 'error', 'adyen-configuration-missing' );
			return;
		}

		try {
			await this.loadScript( config.script.src );
			await this.setupAdyen( config );
		} catch ( error ) {
			this.handleError( error );
		}
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
