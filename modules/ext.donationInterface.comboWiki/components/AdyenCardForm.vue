<template>
	<div class="combo-wiki__card" :class="{ 'combo-wiki__card--loading': !fieldsReady }">
		<div>
			<h2>{{ $i18n( 'combowiki-your-details-heading' ).text() }}</h2>
			<cdx-text-input
				v-model="firstName"
				:placeholder="$i18n( 'donate_interface-donor-first_name' ).text()"
			>
			</cdx-text-input>

			<cdx-text-input
				v-model="lastName"
				:placeholder="$i18n( 'donate_interface-donor-last_name' ).text()"
			>
			</cdx-text-input>

			<cdx-text-input
				v-model="email"
				:placeholder="$i18n( 'donate_interface-donor-email' ).text()"
			>
			</cdx-text-input>
		</div>
		<div id="combo-adyen-card"></div>
		<cdx-button
			action="progressive"
			weight="primary"
			:disabled="!canSubmit"
			@click="submit"
		>
			{{ $i18n( 'donate_interface-submit-button' ).text() }}
		</cdx-button>
	</div>
</template>

<script>
/* global AdyenCheckout */

const { defineComponent } = require( 'vue' );
const { CdxButton, CdxTextInput } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'AdyenCardForm',

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

	emits: [ 'presubmit', 'submit', 'error' ],

	data() {
		return {
			email: this.donation.email,
			firstName: this.donation.firstName,
			lastName: this.donation.lastName,
			checkout: null,
			card: null,
			fieldsReady: false,
			formValid: false,
			wmfToken: mw.config.get( 'wmf_token' )
		};
	},

	computed: {
		canSubmit() {
			return this.formValid && this.detailsComplete;
		},

		detailsComplete() {
			const hasFirstName = Boolean( this.firstName && this.firstName.trim() );
			const hasLastName = Boolean( this.lastName && this.lastName.trim() );
			const hasEmail = this.isValidEmail( this.email );

			return hasFirstName && hasLastName && hasEmail;
		}
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

		isValidEmail( email ) {
			return typeof email === 'string' &&
				/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email.trim() );
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
			if ( !this.card || !this.canSubmit ) {
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
				email: this.email,
				first_name: this.firstName,
				last_name: this.lastName,
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
		const config = mw.config.get( 'adyenConfiguration' );

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
