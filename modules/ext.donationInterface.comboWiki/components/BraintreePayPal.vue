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

module.exports = exports = defineComponent( {
	name: 'BraintreePayPal',
	compilerOptions: {
		// paypal-button is a web component from the PayPal SDK, not a Vue component
		isCustomElement: ( tag ) => tag === 'paypal-button'
	},
	// PaymentMethodForm passes every method the donation and the payment method. This one
	// doesn't use them, so don't put them on the div as HTML attributes.
	inheritAttrs: false,

	props: {
		// The details form above this one, see PaymentMethodForm
		donorDetails: {
			type: Object,
			default: null
		}
	},
	emits: [ 'submit', 'error' ],

	setup( props, ctx ) {
		const params = inject( 'params' );
		const config = params.braintreeConfiguration || {};
		// True once the billing agreement session exists, so start() can run straight
		// away in the click. Safari blocks the popup if anything async runs first.
		const ready = ref( false );
		let paypalCheckout = null;
		let session = null;
		let deviceData = null;

		/**
		 * Turns the approval from PayPal into a nonce and submits the donation with it.
		 *
		 * @param {Object} data From onApprove
		 * @return {Promise}
		 */
		function submitApproved( data ) {
			return paypalCheckout.tokenizePayment( { billingToken: data.billingToken } ).then( ( payload ) => {
				ctx.emit( 'submit', {
					payment_token: payload.nonce,
					device_data: deviceData,
					first_name: payload.details.firstName,
					last_name: payload.details.lastName,
					email: payload.details.email
				} );
			} );
		}

		onMounted( async () => {
			try {
				await loadScripts( config.scripts || [] );
				const client = await braintree.client.create( { authorization: config.clientToken } );
				const dataCollector = await braintree.dataCollector.create( { client } );
				deviceData = dataCollector.deviceData;
				paypalCheckout = await braintree.paypalCheckoutV6.create( { client } );
				await paypalCheckout.loadPayPalSDK();
				const returnUrl = window.location.href;
				session = paypalCheckout.createBillingAgreementSession( {
					billingAgreementDescription: 'Wikimedia Foundation',
					returnUrl: returnUrl,
					cancelUrl: returnUrl,
					onApprove: ( data ) => submitApproved( data ).catch( ( error ) => ctx.emit( 'error', error ) ),
					// The donor closed PayPal. They can click again or choose another method.
					onCancel: () => {},
					onError: ( error ) => ctx.emit( 'error', error )
				} );
				ready.value = true;
			} catch ( error ) {
				ctx.emit( 'error', error );
			}
		} );

		function startPayPal() {
			if ( props.donorDetails && !props.donorDetails.validate() ) {
				return;
			}
			// Popup, or a modal if the browser blocks the popup
			Promise.resolve( session.start( { presentationMode: 'auto' } ) )
				.catch( ( error ) => ctx.emit( 'error', error ) );
		}

		return {
			ready,
			startPayPal
		};
	}
} );
</script>
