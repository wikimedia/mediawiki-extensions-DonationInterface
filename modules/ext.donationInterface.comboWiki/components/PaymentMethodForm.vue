<template>
	<!-- Payment method form component -->
	<div class="fieldset">
		<div class="fieldset__label">
			<p class="text text--base">
				<strong>{{ $i18n( 'combowiki-payment-method-heading' ).text() }}</strong>
			</p>
		</div>
		<div>
			<cdx-button
				v-for="method in availablePaymentMethods"
				:key="method"
				:class="{ 'combo-wiki__option--selected': donation.paymentMethod === method }"
				:disabled="disabled"
				@click="selectPaymentMethod( method )"
			>
				{{ paymentMethodConfig[method].label }}
			</cdx-button>
			<template v-if="paymentMethodConfig[paymentMethod]">
				<div>
					<!-- The details the selected method asks for, shared by all methods -->
					<payment-method-form-fields
						v-if="hasFormFields( paymentMethod )"
						:key="paymentMethod"
						ref="donorFields"
						:donation="donation"
						:form-fields="paymentMethodConfig[paymentMethod].extraFormFields"
						:validation-rules="paymentMethodConfig[paymentMethod].validationRules"
						@update:details="( details ) => $emit( 'onDonorDetailsChange', details )"
						@update:valid="( valid ) => { donorDetails.valid = valid; }"
					></payment-method-form-fields>
					<component
						:is="paymentMethodConfig[paymentMethod].component"
						:donation="donation"
						:payment-method="paymentMethod"
						:donor-details="hasFormFields( paymentMethod ) ? donorDetails : null"
						@submit="paymentMethodConfig[paymentMethod].submit"
						@error="paymentMethodConfig[paymentMethod].error"
						@presubmit="paymentMethodConfig[paymentMethod].presubmit"
					></component>
				</div>
			</template>
		</div>
	</div>
</template>

<script>
const { defineComponent, toRaw, computed, onMounted, inject, reactive, ref } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const api = require( '../api.js' );
const { overrideWithFormEntries } = require( '../utils.js' );
const { isApplePayAvailable, isGooglePayAvailable } = require( '../walletAvailability.js' );
const PaymentMethodFormFields = require( './PaymentMethodFormFields.vue' );
const GravyCardForm = require( './GravyCardForm.vue' );
const GravyRedirectComponent = require( './GravyRedirectComponent.vue' );
const ApplePayComponent = require( './ApplePayComponent.vue' );
const ACHComponent = require( './ACHComponent.vue' );
const AdyenCardForm = require( './AdyenCardForm.vue' );
const GooglePayComponent = require( './GooglePayComponent.vue' );
const SEPAComponent = require( './SEPAComponent.vue' );
const BraintreePayPal = require( './BraintreePayPal.vue' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'PaymentMethodSelector',
	components: {
		'cdx-button': CdxButton,
		'payment-method-form-fields': PaymentMethodFormFields,
		'card-form': GravyCardForm,
		'paypal-form': GravyRedirectComponent,
		'venmo-form': GravyRedirectComponent,
		'applepay-form': ApplePayComponent,
		'googlepay-form': GooglePayComponent,
		'ach-form': ACHComponent,
		'adyen-card-form': AdyenCardForm,
		'braintree-paypal-form': BraintreePayPal,
		'sepa-form': SEPAComponent
	},
	props: {
		donation: {
			type: Object,
			required: true
		},
		disabled: {
			type: Boolean,
			default: false
		}
	},
	emits: [ 'donationSuccess', 'donationError', 'onPaymentMethodChange', 'onDonorDetailsChange' ],

	setup( props, ctx ) {
		const appState = useAppState();
		const params = inject( 'params' );
		const formFieldsForMethod = ( params.DonationInterfaceFormFields || {} ).method || {};
		const rulesForMethod = ( params.DonateValidationRules || {} ).method || {};

		/**
		 * @param {string} paymentMethod ComboWiki payment option as defined in paymentMethodConfig
		 * @return {Object} The form fields for that payment method, filtered by country
		 */
		const getFormFieldsFor = ( paymentMethod ) => {
			const mapping = api.paymentMethodMap[ paymentMethod ];
			return ( mapping && formFieldsForMethod[ mapping.submethod || mapping.method ] ) || {};
		};

		/**
		 * @param {string} paymentMethod ComboWiki payment option as defined in paymentMethodConfig
		 * @return {Object} The validation rules for that payment method's fields
		 */
		const getValidationRulesFor = ( paymentMethod ) => {
			const mapping = api.paymentMethodMap[ paymentMethod ];
			return ( mapping && rulesForMethod[ mapping.submethod || mapping.method ] ) || {};
		};

		// The selected method's details form, rendered once above the method's component
		const donorFields = ref();
		/**
		 * What the selected method's component knows of the details form, passed as its
		 * donorDetails prop. A method without form fields gets null instead.
		 *
		 * @property {boolean} valid Whether the details are valid, kept up to date by the form
		 * @property {Function} validate Shows the errors and returns whether the details are valid;
		 *  called by the component before it submits or opens a payment sheet
		 * @property {Function} getValues The details keyed by server field name
		 */
		const donorDetails = reactive( {
			valid: true,
			validate: () => !donorFields.value || donorFields.value.validate(),
			getValues: () => ( donorFields.value ? donorFields.value.getValues() : {} )
		} );

		/**
		 * Submits the donation via the API and emits donationSuccess/donationError with the result.
		 *
		 * @param {Object} [payload] Extra payment-method-specific data to merge into the donation params.
		 * @param {Function} [successCallback] Called with the API result on success.
		 * @param {Function} [errorCallback] Called with the failure details on error.
		 */
		const submitDonation = ( payload = {}, successCallback = null, errorCallback = null ) => {
			// What the donor entered in the details form wins over e.g. a wallet's address
			payload = overrideWithFormEntries( payload, donorDetails.getValues() );
			appState.setLoading( true );
			api.submitDonation( toRaw( props.donation ), payload )
				.then( ( result ) => {
					// how do we want to display validation errors
					if ( result.result.isFailed || result.result.errors ) {
						ctx.emit( 'donationError', result.result.errors );
					}
					if ( successCallback ) {
						successCallback( result );
					}
					ctx.emit( 'donationSuccess', result );
					appState.setLoading( false );
				} )
				.catch( ( code, failure ) =>  {
					ctx.emit( 'donationError', code, failure );
					if ( errorCallback ) {
						errorCallback( failure );
						// what should happen here, need to handle it properly
					}
					appState.setLoading( false );
				} );
		};

		/**
		 * Validates the Apple Pay merchant session with the API before the payment sheet can proceed.
		 *
		 * @param {Object} event The Apple Pay merchant validation event.
		 * @param {Function} successCallback Called with the validated session data.
		 * @param {Function} failureCallback Called with the error when validation fails.
		 */
		const validateApplePay = ( event, successCallback, failureCallback ) => {
			appState.setLoading( true );
			const payload = Object.assign( {}, props.donation );
			payload.validationURL = event.validationURL;
			api.validateApplePayPaymentSession( payload )
				.then( ( data ) => {
					if ( data.result && data.result.errors ) {
						failureCallback( data.result.errors );
						mw.log( 'Apple Pay validation failure: ' + JSON.stringify( data.result.errors ) );
					} else {
						successCallback( data );
					}
					appState.setLoading( false );
				} ).catch( ( e ) => {
					failureCallback( e );
					console.log( 'Error', e );
					mw.log( 'Apple Pay validation failure: ' + e );
					appState.setLoading( false );
				} );
		};

		/**
		 * Creates a Gravy checkout session for the card form before the SecureFields SDK is set up.
		 *
		 * @param {Object} parameters Parameters used to create the checkout session.
		 * @param {Function} successCallback Called with the new session ID.
		 * @param {Function} failureCallback Called when session creation fails.
		 */
		const getGravyCheckoutSession = ( parameters, successCallback, failureCallback ) => {
			appState.setLoading( true );
			api.createCheckoutSession( parameters )
				.then( ( sessionId ) => {
					successCallback( sessionId );
					appState.setLoading( false );
				} )
				.catch( () => {
					failureCallback();
					appState.setLoading( false );
				} );
		};

		const getAdyenCheckoutSession = ( parameters, successCallback, failureCallback ) => {
			appState.setLoading( true );
			api.createCheckoutSession( parameters )
				.then( ( sessionId ) => {
					successCallback( sessionId );
					appState.setLoading( false );
				} )
				.catch( () => {
					failureCallback();
					appState.setLoading( false );
				} );
		};

		/**
		 * Forwards a payment-method error up to the parent as a donationError event.
		 *
		 * @param {*} reason The error/reason to forward.
		 */
		const onError = ( reason ) => {
			ctx.emit( 'donationError', reason );
		};

		/**
		 * Per-method UI and behavior configuration, keyed by payment method.
		 *
		 * @property {string} label Display text for the payment method's selector button.
		 * @property {string} component Name of the registered component that renders the method's form.
		 * @property {boolean} supportsOneTime Flag to show support for one time transaction (default: True).
		 * @property {boolean} supportsMonthly Flag to show support for monthly recurring transaction (default: True).
		 * @property {boolean} supportsAnnual Flag to show support for Annual recurring transaction (default: True).
		 * @property {Function} submit Called to submit the donation once the payment method's form is complete.
		 * @property {Function} [error] Called when the payment method reports an error.
		 * @property {Function} [presubmit] Called before submit to prepare the payment method (e.g. create a session or validate).
		 * @property {Function} [isAvailable] Resolves to whether the donor's browser can use the method
		 *  (e.g. a wallet); the method is only shown once it resolves to true. Without it, always available.
		 * @property {Object} [extraFormFields] Extra form fields to include in the payment method form, already filtered by country.
		 */
		const paymentMethodConfig = {
			card: {
				label: mw.message( 'combowiki-method-card' ).text(),
				component: 'card-form',
				submit: submitDonation,
				error: onError,
				presubmit: getGravyCheckoutSession,
				extraFormFields: getFormFieldsFor( 'card' ),
				validationRules: getValidationRulesFor( 'card' )
			},
			adyen_card: {
				label: mw.message( 'combowiki-method-adyen-card' ).text(),
				component: 'adyen-card-form',
				gateway: 'adyen',
				submit: submitDonation,
				error: onError,
				presubmit: getAdyenCheckoutSession,
				extraFormFields: getFormFieldsFor( 'adyen_card' ),
				validationRules: getValidationRulesFor( 'adyen_card' )
			},
			paypal: {
				label: mw.message( 'combowiki-method-paypal' ).text(),
				component: 'paypal-form',
				error: onError,
				submit: submitDonation,
				extraFormFields: getFormFieldsFor( 'paypal' ),
				validationRules: getValidationRulesFor( 'paypal' )
			},
			braintree_paypal: {
				label: mw.message( 'combowiki-method-paypal' ).text(),
				component: 'braintree-paypal-form',
				error: onError,
				submit: submitDonation,
				extraFormFields: getFormFieldsFor( 'braintree_paypal' ),
				validationRules: getValidationRulesFor( 'braintree_paypal' )
			},
			venmo: {
				label: mw.message( 'combowiki-method-venmo' ).text(),
				component: 'venmo-form',
				error: onError,
				submit: submitDonation,
				extraFormFields: getFormFieldsFor( 'venmo' ),
				validationRules: getValidationRulesFor( 'venmo' )
			},
			applepay: {
				label: mw.message( 'combowiki-method-applepay' ).text(),
				component: 'applepay-form',
				submit: submitDonation,
				error: onError,
				presubmit: validateApplePay,
				isAvailable: () => isApplePayAvailable( params.gravyConfiguration ),
				extraFormFields: getFormFieldsFor( 'applepay' ),
				validationRules: getValidationRulesFor( 'applepay' )
			},
			ach: {
				label: mw.message( 'combowiki-method-ach' ).text(),
				component: 'ach-form',
				submit: submitDonation,
				error: onError,
				supportsOneTime: false,
				extraFormFields: getFormFieldsFor( 'ach' ),
				validationRules: getValidationRulesFor( 'ach' )
			},
			googlepay: {
				label: mw.message( 'combowiki-method-googlepay' ).text(),
				component: 'googlepay-form',
				submit: submitDonation,
				error: onError,
				isAvailable: () => isGooglePayAvailable( params.gravyConfiguration ),
				extraFormFields: getFormFieldsFor( 'googlepay' ),
				validationRules: getValidationRulesFor( 'googlepay' )
			},
			adyen_googlepay: {
				label: mw.message( 'combowiki-method-googlepay' ).text(),
				component: 'googlepay-form',
				submit: submitDonation,
				error: onError,
				gateway: 'adyen',
				isAvailable: () => isGooglePayAvailable( params.adyenConfiguration ),
				extraFormFields: getFormFieldsFor( 'adyen_googlepay' ),
				validationRules: getValidationRulesFor( 'adyen_googlepay' )
			},
			sepadirectdebit: {
				label: mw.message( 'combowiki-method-sepa' ).text(),
				component: 'sepa-form',
				submit: submitDonation,
				error: onError,
				extraFormFields: getFormFieldsFor( 'sepadirectdebit' ),
				validationRules: getValidationRulesFor( 'sepadirectdebit' )
			}
		};

		/**
		 * @param {string} method Server payment method code
		 * @param {string} [submethod] Server payment submethod code
		 * @return {string} Lookup key for gatewayByMethod
		 */
		const methodKey = ( method, submethod ) => submethod ? method + '/' + submethod : method;

		// Server payment (sub)method => the gateway that handles it,
		// e.g. { cc: 'gravy', rtbt: 'gravy', 'rtbt/sepadirectdebit': 'gravy' }
		const gatewayByMethod = {};
		for ( const entry of params.paymentMethods ) {
			gatewayByMethod[ methodKey( entry.method, entry.submethod ) ] = entry.gateway;
		}

		/**
		 * @param {string} method Payment method key
		 * @return {boolean} Whether the server offers the method (and submethod, if the option
		 *  is one) on the gateway of the method for this country
		 */
		const isOfferedByServer = ( method ) => {
			const mapping = api.paymentMethodMap[ method ];
			return !!mapping && gatewayByMethod[ methodKey( mapping.method, mapping.submethod ) ] === mapping.gateway;
		};

		// Answers from the isAvailable checks, keyed by payment method: true once the
		// browser can use the method, false if it can't
		const availability = reactive( {} );

		/**
		 * Returns the payment methods offered for the current donation.
		 * Using computed for the caching property to ensure the list is only regenerated when
		 * the donation frequency is changed.
		 *
		 * @return {string[]} The list of available payment method keys.
		 */
		const availablePaymentMethods = computed( () => {
			const isOneTime = props.donation.frequency === 'once';
			const methods = [];
			for ( const [ method, config ] of Object.entries( paymentMethodConfig ) ) {
				if ( isOneTime && config.supportsOneTime === false ) {
					continue;
				}
				if ( !isOfferedByServer( method ) ) {
					continue;
				}
				// Not shown until its check says the browser can use it, nor after it says it can't
				if ( config.isAvailable && availability[ method ] !== true ) {
					continue;
				}
				methods.push( method );
			}
			return methods;
		} );

		/**
		 * @param {string} method Payment method key
		 * @return {boolean} Whether the method has form fields for the donor to fill in
		 */
		const hasFormFields = ( method ) => {
			const config = paymentMethodConfig[ method ];
			return !!config && Object.keys( config.extraFormFields ).length > 0;
		};

		// A ref, so the form updates when the selection changes here (e.g. in markUnavailable)
		const selectedPaymentMethod = ref( props.donation.paymentMethod || '' );

		/**
		 * Sets the active payment method and notifies the parent via onPaymentMethodChange.
		 *
		 * @param {string} method The selected payment method key.
		 */
		const selectPaymentMethod = ( method ) => {
			selectedPaymentMethod.value = method;
			ctx.emit( 'onPaymentMethodChange', method );
		};

		/**
		 * Never offers a method the donor's browser can't use: no button, no form and
		 * no error. If the donor had selected it (e.g. from the URL), clears the selection
		 * so they pick another method. Not card on their behalf: it may be before they
		 * chose an amount, which the card form needs to create its checkout session.
		 *
		 * @param {string} method The payment method key.
		 */
		const markUnavailable = ( method ) => {
			availability[ method ] = false;
			if ( selectedPaymentMethod.value === method ) {
				selectPaymentMethod( '' );
			}
		};

		onMounted( () => {
			// Ask about every offered method with a check, whatever the frequency, so a
			// wallet is ready to show (or stays hidden) when the donor changes it
			for ( const [ method, config ] of Object.entries( paymentMethodConfig ) ) {
				if ( config.isAvailable && isOfferedByServer( method ) ) {
					config.isAvailable().then( ( available ) => {
						if ( available ) {
							availability[ method ] = true;
						} else {
							markUnavailable( method );
						}
					} );
				}
			}
		} );

		return {
			selectPaymentMethod,
			availablePaymentMethods,
			paymentMethod: selectedPaymentMethod,
			paymentMethodConfig,
			donorFields,
			donorDetails,
			hasFormFields
		};
	}
} );
</script>
