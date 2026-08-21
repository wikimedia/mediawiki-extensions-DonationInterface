<template>
	<!--    Payment method form component-->
	<div>
		<h2>{{ $i18n( 'combowiki-payment-method-heading' ).text() }}</h2>

		<cdx-button
			v-for="method in availablePaymentMethods"
			:key="method"
			:class="{ 'combo-wiki__option--selected': donation.paymentMethod === method }"
			:disabled="disabled"
			@click="selectPaymentMethod( method )"
		>
			{{ paymentMethodConfig[method].label }}
		</cdx-button>
		<br>
		<component
			:is="paymentMethodConfig[paymentMethod].component"
			v-if="paymentMethodConfig[paymentMethod]"
			:donation="donation"
			@submit="paymentMethodConfig[paymentMethod].submit"
			@error="paymentMethodConfig[paymentMethod].error"
			@presubmit="paymentMethodConfig[paymentMethod].presubmit"
		></component>
	</div>
</template>

<script>
const { defineComponent, toRaw, computed } = require( 'vue' );
const { CdxButton } = require( '@wikimedia/codex' );
const api = require( '../api.js' );
const GravyCardForm = require( './GravyCardForm.vue' );
const GravyBraintreeComponent = require( './GravyBraintreeComponent.vue' );
const ApplePayComponent = require( './ApplePayComponent.vue' );
const ACHComponent = require( './ACHComponent.vue' );
const GooglePayComponent = require( './GooglePayComponent.vue' );
const { useAppState } = require( '../composables/useAppState.js' );

module.exports = exports = defineComponent( {
	name: 'PaymentMethodSelector',
	components: {
		'cdx-button': CdxButton,
		'card-form': GravyCardForm,
		'paypal-form': GravyBraintreeComponent,
		'venmo-form': GravyBraintreeComponent,
		'applepay-form': ApplePayComponent,
		'googlepay-form': GooglePayComponent,
		'ach-form': ACHComponent
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
	emits: [ 'donationSuccess', 'donationError', 'onPaymentMethodChange' ],

	setup( props, ctx ) {
		const appState = useAppState();

		/**
		 * Submits the donation via the API and emits donationSuccess/donationError with the result.
		 *
		 * @param {Object} [payload] Extra payment-method-specific data to merge into the donation params.
		 * @param {Function} [successCallback] Called with the API result on success.
		 * @param {Function} [errorCallback] Called with the failure details on error.
		 */
		const submitDonation = ( payload = {}, successCallback = null, errorCallback = null ) => {
			appState.setLoading( true );
			api.submitDonation( toRaw( props.donation ), payload )
				.then( ( result ) => {
					console.log( 'submitDonation', result );
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
		 */
		const paymentMethodConfig = {
			card: {
				label: mw.message( 'combowiki-method-card' ).text(),
				component: 'card-form',
				submit: submitDonation,
				error: onError,
				presubmit: getGravyCheckoutSession
			},
			paypal: {
				label: mw.message( 'combowiki-method-paypal' ).text(),
				component: 'paypal-form',
				error: onError,
				submit: submitDonation
			},
			venmo: {
				label: mw.message( 'combowiki-method-venmo' ).text(),
				component: 'venmo-form',
				error: onError,
				submit: submitDonation
			},
			applepay: {
				label: mw.message( 'combowiki-method-applepay' ).text(),
				component: 'applepay-form',
				submit: submitDonation,
				error: onError,
				presubmit: validateApplePay
			},
			ach: {
				label: mw.message( 'combowiki-method-ach' ).text(),
				component: 'ach-form',
				submit: submitDonation,
				error: onError,
				supportsOneTime: false
			},
			googlepay: {
				label: mw.message( 'combowiki-method-googlepay' ).text(),
				component: 'googlepay-form',
				submit: submitDonation,
				error: onError
			}
		};

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
				methods.push( method );
			}
			return methods;
		} );

		return {
			/**
			 * Sets the active payment method and notifies the parent via onPaymentMethodChange.
			 *
			 * @param {string} method The selected payment method key.
			 */
			selectPaymentMethod( method ) {
				this.paymentMethod = method;
				ctx.emit( 'onPaymentMethodChange', method );
			},
			availablePaymentMethods,
			paymentMethod: props.donation.paymentMethod || '',
			paymentMethodConfig
		};
	}
} );
</script>
