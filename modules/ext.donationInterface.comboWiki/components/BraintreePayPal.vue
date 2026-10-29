<template>
	<div class="payment-method-form" data-autoscroll>
		<!-- PayPal's own button, defined by the PayPal SDK that loadPayPalSDK() loads -->
		<paypal-button
			v-show="ready"
			type="pay"
			class="combo-wiki__button-submit"
			@click="startPayPal"
		></paypal-button>
	</div>
</template>

<script>
/* global braintree */
const { defineComponent, inject, onMounted, ref } = require( 'vue' );
const { useAppState } = require( '../composables/useAppState.js' );

// PayPal adds these to returnUrl when it sends the donor back from the redirect
const PAYPAL_RETURN_PARAMS = [ 'token', 'ba_token' ];
// Added to cancelUrl, so the page knows not to send the donor to PayPal again
const CANCEL_PARAM = 'paypalCancel';

// Loaded once per page, in order: the PayPal and device data scripts need the client script
let scriptsLoaded = null;

/**
 * @param {string[]} scripts The Braintree script URLs, in the order to load them
 * @return {Promise} Resolves once every script has loaded
 */
function loadScripts( scripts ) {
	if ( !scriptsLoaded ) {
		scriptsLoaded = scripts.reduce(
			( previous, src ) => previous.then( () => mw.loader.getScript( src ) ),
			Promise.resolve()
		);
	}
	return scriptsLoaded;
}

/**
 * @param {string[]} names Query params to leave out
 * @return {URL} The address of this page without them
 */
function pageUrlWithout( names ) {
	const url = new URL( window.location.href );
	names.forEach( ( name ) => url.searchParams.delete( name ) );
	return url;
}

module.exports = exports = defineComponent( {
	name: 'BraintreePayPal',
	compilerOptions: {
		// paypal-button is a web component from the PayPal SDK, not a Vue component
		isCustomElement: ( tag ) => tag === 'paypal-button'
	},
	// PaymentMethodForm also passes payment-method, which this component doesn't use,
	// so don't put it on the div as an HTML attribute.
	inheritAttrs: false,

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

	setup( props, ctx ) {
		const params = inject( 'params' );
		const appState = useAppState();
		const config = params.braintreeConfiguration || {};
		const urlParams = new URLSearchParams( window.location.search );
		// True once the billing agreement session exists, so start() can run straight
		// away in the click. Safari blocks the popup if anything async runs first.
		const ready = ref( false );
		let paypalCheckout = null;
		let session = null;
		let deviceData = null;

		const emitError = ( error ) => ctx.emit( 'error', error );
		const donorDetailsValid = () => !props.donorDetails || props.donorDetails.validate();

		/**
		 * Turns the billing token from PayPal into a nonce and submits the donation with it.
		 * Only the billing token: with an orderId as well, PayPal declines the payment.
		 *
		 * @param {string} billingToken
		 * @return {Promise}
		 */
		function submitApproved( billingToken ) {
			return paypalCheckout.tokenizePayment( { billingToken } ).then( ( payload ) => {
				ctx.emit( 'submit', {
					payment_token: payload.nonce,
					device_data: deviceData,
					first_name: payload.details.firstName,
					last_name: payload.details.lastName,
					email: payload.details.email
				} );
			} ).catch( emitError );
		}

		/**
		 * @param {string} presentationMode 'auto' for a popup, 'redirect' for a full page
		 */
		function startSession( presentationMode ) {
			Promise.resolve( session.start( { presentationMode } ) ).catch( emitError );
		}

		/**
		 * Loads the Braintree scripts and the PayPal SDK, and creates the billing agreement session.
		 */
		async function setUpPayPal() {
			await loadScripts( config.scripts || [] );
			const client = await braintree.client.create( { authorization: config.clientToken } );
			deviceData = ( await braintree.dataCollector.create( { client } ) ).deviceData;
			paypalCheckout = await braintree.paypalCheckoutV6.create( { client } );
			await paypalCheckout.loadPayPalSDK();

			// Only used by the redirect: the page loads again with the donation in the URL
			const returnUrl = pageUrlWithout( [ ...PAYPAL_RETURN_PARAMS, CANCEL_PARAM ] );
			const cancelUrl = new URL( returnUrl );
			cancelUrl.searchParams.set( CANCEL_PARAM, '1' );
			session = paypalCheckout.createBillingAgreementSession( {
				billingAgreementDescription: 'Wikimedia Foundation',
				returnUrl: returnUrl.toString(),
				cancelUrl: cancelUrl.toString(),
				onApprove: ( data ) => submitApproved( data.billingToken ),
				// The donor closed PayPal. They can click again or choose another method.
				onCancel: () => {},
				onError: emitError
			} );
		}

		/**
		 * A complete gift with PayPal chosen in the URL, and the donor hasn't cancelled
		 * PayPal before. paypalCancel stays in the URL, so a reload shows the button.
		 *
		 * @return {boolean}
		 */
		function isZeroClick() {
			return urlParams.get( 'payment_method' ) === 'paypal' &&
				!urlParams.has( CANCEL_PARAM ) &&
				props.donation.frequency !== null &&
				Number( props.donation.amount ) > 0 &&
				donorDetailsValid();
		}

		onMounted( async () => {
			try {
				await setUpPayPal();
			} catch ( error ) {
				emitError( error );
				return;
			}

			if ( PAYPAL_RETURN_PARAMS.some( ( name ) => urlParams.has( name ) ) ) {
				// So a reload can't submit a used billing token again
				window.history.replaceState( null, '', pageUrlWithout( PAYPAL_RETURN_PARAMS ).toString() );
			}

			const billingToken = urlParams.get( 'ba_token' );
			if ( billingToken ) {
				// Back from the redirect
				appState.setLoading( true );
				submitApproved( billingToken );
			} else if ( isZeroClick() ) {
				// The donor chose PayPal before arriving (banner or email), so go straight to PayPal
				appState.setLoading( true );
				startSession( 'redirect' );
				return;
			}
			ready.value = true;
		} );

		function startPayPal() {
			if ( donorDetailsValid() ) {
				// Popup, or a modal if the browser blocks the popup
				startSession( 'auto' );
			}
		}

		return {
			ready,
			startPayPal
		};
	}
} );
</script>
