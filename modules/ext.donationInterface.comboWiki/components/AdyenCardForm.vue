<template>
	<div class="combo-wiki__card payment-method-form" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div id="combo-adyen-card"></div>
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
		setLocaleAndTranslations( config, localeFromServer ) {
			const adyenSupportedLocale = [
					'zh-CN', 'zh-TW', 'hr-HR',
					'cs-CZ', 'da-DK', 'nl-NL',
					'en-US', 'fi-FI', 'fr-FR',
					'de-DE', 'el-GR', 'hu-HU', 'it-IT',
					'ja-JP', 'ko-KR', 'no-NO', 'pl-PL',
					'pt-BR', 'ro-RO', 'ru-RU', 'sk-SK',
					'sl-SL', 'es-ES', 'sv-SE'
				],
				baseLocaleFromServer = localeFromServer.slice( 0, 2 );

			// We support Norwegian Bokmal (nb) but Adyen's components just support the generic 'no' Norwegian code
			if ( baseLocaleFromServer === 'nb' ) {
				config.locale = 'no-NO';
			} else {
				config.locale = localeFromServer;
			}

			config.translations = {};
			// Check if donor's language is unsupported by Adyen and we need to provide our own customized translation
			// Adyen supports ar as Arabic - International and doesn't check the country part
			if ( baseLocaleFromServer !== 'ar' && !adyenSupportedLocale.includes( config.locale ) ) {
				config.translations[ config.locale ] = {
					//title
					'creditCard.cardNumber.label': mw.msg( 'donate_interface-credit-card-number' ),
					'creditCard.expiryDate.label': mw.msg( 'donate_interface-credit-card-expiration' ),
					'creditCard.securityCode.label': mw.msg( 'donate_interface-cvv' ),
					//Adyen no longer uses placeholders in v6, they use contextualText instead
					'creditCard.expiryDate.contextualText': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
					'creditCard.securityCode.contextualText.3digits': mw.msg( 'donate_interface-cvv-placeholder-3-digits' ),
					'creditCard.securityCode.contextualText.4digits': mw.msg( 'donate_interface-cvv-placeholder-4-digits' ),
					//error
					'creditCard.numberField.invalid': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
					'creditCard.expiryDateField.invalid': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
					'error.va.gen.01': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
					'error.va.gen.02': mw.msg( 'donate_interface-error-msg-field-not-valid' ),
					'error.va.sf-cc-num.01': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
					'error.va.sf-cc-num.02': mw.msg( 'donate_interface-error-msg-card-number-do-not-match-card-brand' ),
					'error.va.sf-cc-num.03': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
					'error.va.sf-cc-dat.01': mw.msg( 'donate_interface-error-msg-card-too-old' ),
					'error.va.sf-cc-dat.02': mw.msg( 'donate_interface-error-msg-date-too-far-in-the-future' ),
					// Adyen v6 error codes
					// https://github.com/Adyen/adyen-web/blob/a05c1184195fa73ce7dc9e956fa080cb40638209/packages/server/translations/en-US.json
					'cc.num.900': mw.msg( 'donate_interface-error-msg-card-num' ),
					'cc.num.901': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
					'cc.num.902': mw.msg( 'donate_interface-error-msg-invalid-card-number' ),
					'cc.num.903': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
					'cc.num.904': mw.msg( 'donate_interface-error-msg-unsupported-card-entered' ),
					'cc.dat.910': mw.msg( 'donate_interface-error-msg-expiration' ),
					'cc.dat.911': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
					'cc.dat.912': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
					'cc.dat.913': mw.msg( 'donate_interface-error-msg-expiry-date-field-invalid' ),
					'cc.cvc.920': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
					'cc.cvc.921': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
					'cc.cvc.920.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
					'cc.cvc.921.amex': mw.msg( 'donate_interface-error-msg-invalid-cvv-format' ),
					'err.gen.9100': mw.msg( 'donate_interface-error-msg-incomplete-field' ),
					'err.gen.9101': mw.msg( 'donate_interface-error-msg-field-not-valid' )
				};
			} else if ( config.locale === 'nl-NL' ) {
				config.translations[ config.locale ] = {
					'idealIssuer.selectField.placeholder': mw.msg( 'donate_interface-rtbt-issuer_id' )
				};
			} else if ( this.params.language === 'ja' ) {
				config.translations[ config.locale ] = {
					'creditCard.expiryDateField.placeholder': mw.msg( 'donate_interface-expiry-date-field-placeholder' ),
					'creditCard.cvcField.placeholder.3digits': '',
					'creditCard.cvcField.placeholder.4digits': ''
				};
			} else {
				config.translations[ config.locale ] = {};
			}
			if ( mw.config.get( 'payment_submethod' ) === 'ach' ) {
				config.translations[ config.locale ] = {
					'error.va.sf-ach-loc.02': mw.msg( 'donate_interface-ach-invalid-routing-number-count' ),
					'error.va.sf-ach-num.02': mw.msg( 'donate_interface-ach-invalid-account-number-count' )
				};
			}

			// if sepa, update holder name to account holder name
			if ( mw.config.get( 'payment_submethod' ) === 'sepadirectdebit' ) {
				config.translations[ config.locale ][ 'sepa.ownerName' ] = mw.msg( 'donate_interface-bt-account_holder' );
			}
		},

		async setupAdyen( config ) {
			try {
				const checkoutConfig = {
					clientKey: config.clientKey,
					environment: config.environment,
					locale: config.locale,
					paymentMethodsResponse: config.paymentMethodsResponse,
					countryCode: this.params.country,
					showPayButton: false,
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
				const { AdyenCheckout } = window.AdyenWeb;
				this.setLocaleAndTranslations( checkoutConfig, config.locale );
				this.checkout = await AdyenCheckout( checkoutConfig );

				this.card = new window.AdyenWeb.Card( this.checkout, {
					showBrandsUnderCardNumber: false,
					locale: checkoutConfig.locale
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
