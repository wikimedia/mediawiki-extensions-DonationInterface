<template>
	<div
		id="braintree-paypal"
		class="payment-method-form"
		data-autoscroll
	>
		<!-- PayPal's own button, defined by the PayPal SDK that loadPayPalSDK() loads -->
		<method-submit>
			<paypal-button
				v-show="ready"
				ref="paypalButton"
				type="donate"
				class="combo-wiki__button-submit paypal-button"
				@click="startPayPal"
			></paypal-button>
		</method-submit>
	</div>
</template>

<script>
/* global braintree */
const { defineComponent, inject, onMounted, ref, watch } = require( 'vue' );
const { hasValidWalletName, hasValidWalletEmail, showWalletFallbackFields } = require( 'ext.donationInterface.walletFallback' );
const { useAppState } = require( '../composables/useAppState.js' );
const MethodSubmit = require( './MethodSubmit.vue' );

// PayPal adds these to returnUrl when it sends the donor back from the redirect:
// token and ba_token for a billing agreement which is then used for braintree vaulting
// token (the order) and PayerID for one-time
const PAYPAL_RETURN_PARAMS = [ 'token', 'ba_token', 'PayerID' ];
// Added to cancelUrl, so the page knows not to send the donor to PayPal again
const CANCEL_PARAM = 'paypalCancel';

// How long to wait for PayPal to add the Donate text to its button, see whenLabelDrawn().
const LABEL_WAIT_MS = 1000;

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
 * PayPal draws its button first and adds the translated "Donate" text a moment later,
 * once it has fetched the translation, and the button widens with it. Waits for the
 * text, so PaymentMethodForm doesn't show the buttons just before PayPal's grows and
 * pushes the others aside. Gives up after LABEL_WAIT_MS.
 *
 * @param {HTMLElement} button The paypal-button element
 * @return {Promise} Resolves once the text is there, or after LABEL_WAIT_MS
 */
function whenLabelDrawn( button ) {
	const hasLabel = () => {
		const text = button.shadowRoot.querySelector( 'svg.label text' );
		return !!text && text.textContent.trim() !== '';
	};
	if ( !button.shadowRoot || hasLabel() ) {
		return Promise.resolve();
	}
	return new Promise( ( resolve ) => {
		let timer = null;
		const observer = new MutationObserver( () => {
			if ( hasLabel() ) {
				done();
			}
		} );
		function done() {
			observer.disconnect();
			clearTimeout( timer );
			resolve();
		}
		observer.observe( button.shadowRoot, { subtree: true, childList: true, characterData: true } );
		timer = setTimeout( done, LABEL_WAIT_MS );
	} );
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
	components: {
		'method-submit': MethodSubmit
	},
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
	emits: [ 'submit', 'error', 'ready' ],

	setup( props, ctx ) {
		const params = inject( 'params' );
		const appState = useAppState();
		const config = params.braintreeConfiguration || {};
		const urlParams = new URLSearchParams( window.location.search );
		// True once the PayPal SDK has loaded and the session exists, so start() can run
		// straight away in the click. Safari blocks the popup if anything async runs first.
		const ready = ref( false );
		const paypalButton = ref();
		let paypalCheckout = null;
		let deviceData = null;
		// Made ahead of the click, and made again when the donation changes, see prepareSession()
		let session = null;
		// Why the session couldn't be made, reported when the donor clicks
		let sessionError = null;
		// Only used by the redirect: the page loads again with the donation in the URL
		let returnUrl = null;
		let cancelUrl = null;

		const emitError = ( error ) => ctx.emit( 'error', error );
		const donorDetailsValid = () => !props.donorDetails || props.donorDetails.validate();

		/**
		 * Create the tokenized single use nonce that is used in both one-time
		 * and recurring (vaulted) payments
		 *
		 * @param {Object} approved { billingToken } for recurring, { orderId, payerId } for one-time
		 * @return {Promise}
		 */
		function submitApproved( approved ) {
			return paypalCheckout.tokenizePayment( approved ).then( ( payload ) => {
				const sendData = {
					payment_token: payload.nonce,
					device_data: deviceData,
					first_name: payload.details.firstName,
					last_name: payload.details.lastName,
					email: payload.details.email
				};

				if ( !hasValidWalletName( sendData.first_name ) ||
					!hasValidWalletName( sendData.last_name ) ||
					!hasValidWalletEmail( sendData.email ) ) {
					showWalletFallbackFields(
						$( '#braintree-paypal' ),
						sendData.email,
						( firstName, lastName, email ) => {
							sendData.first_name = firstName;
							sendData.last_name = lastName;
							sendData.email = email;
							ctx.emit( 'submit', sendData );
						}
					);
					return;
				}

				ctx.emit( 'submit', sendData );
			} ).catch( emitError );
		}

		/**
		 * Handle both one-time and recurring (vaulted)
		 *
		 * The recurring donation is vaulted and a billing agreement is created
		 * A one-time donation is a plain PayPal checkout
		 *
		 * @return {Object} The PayPal session, made synchronously
		 */
		function createSession() {
			const donation = props.donation;
			const shared = {
				returnUrl,
				cancelUrl,
				// The donor closed PayPal. They can click again or choose another method.
				onCancel: () => {},
				onError: emitError
			};
			if ( donation.frequency === 'once' ) {
				return paypalCheckout.createOneTimePaymentSession( Object.assign( {
					amount: String( donation.amount ),
					currency: donation.currency,
					// Authorize, then the server captures, as with the legacy form
					intent: 'authorize',
					onApprove: ( data ) => submitApproved( { orderId: data.orderId, payerId: data.payerId } )
				}, shared ) );
			}
			return paypalCheckout.createBillingAgreementSession( Object.assign( {
				billingAgreementDescription: 'Wikimedia Foundation',
				onApprove: ( data ) => submitApproved( { billingToken: data.billingToken } )
			}, shared ) );
		}

		/**
		 * Makes the session for the donation on the form before the donor clicks, so the
		 * click only has to call start(). Making it in the click is too slow for Safari,
		 * which then blocks the popup.
		 */
		function prepareSession() {
			try {
				session = createSession();
				sessionError = null;
			} catch ( error ) {
				// Creating the session throws on invalid options, e.g. no amount
				session = null;
				sessionError = error;
			}
		}

		// Remakes the session when the donation changes. Made in setup() so Vue stops it
		// when the component unmounts, e.g. when the donor changes the payment method;
		// a watch made after an await isn't stopped.
		// 'sync' so any changes made on the form are in the session.
		watch(
			() => [ props.donation.frequency, props.donation.amount, props.donation.currency ],
			() => {
				// Until setUpPayPal() has finished, which then makes the first session itself
				if ( paypalCheckout ) {
					prepareSession();
				}
			},
			{ flush: 'sync' }
		);

		/**
		 * Opens PayPal for the donation on the form. Must run synchronously in the
		 * donor's click, unless redirecting.
		 *
		 * @param {string} presentationMode 'auto' for a popup, 'redirect' for a full page
		 */
		function startSession( presentationMode ) {
			if ( !session ) {
				emitError( sessionError );
				return;
			}
			Promise.resolve( session.start( { presentationMode } ) ).catch( emitError );
		}

		/**
		 * Loads the Braintree scripts and the PayPal SDK, and makes the session.
		 */
		async function setUpPayPal() {
			await loadScripts( config.scripts || [] );
			const client = await braintree.client.create( { authorization: config.clientToken } );
			deviceData = ( await braintree.dataCollector.create( { client } ) ).deviceData;
			const checkout = await braintree.paypalCheckoutV6.create( { client } );
			await checkout.loadPayPalSDK();

			const pageUrl = pageUrlWithout( [ ...PAYPAL_RETURN_PARAMS, CANCEL_PARAM ] );
			returnUrl = pageUrl.toString();
			pageUrl.searchParams.set( CANCEL_PARAM, '1' );
			cancelUrl = pageUrl.toString();

			// Set last so the watch only makes sessions once the SDK and URLs are ready
			paypalCheckout = checkout;
			prepareSession();
		}

		/**
		 * @return {Object|null} What to tokenize when PayPal sent the donor back from the
		 *  redirect: the billing token, or the order and payer for a one-time donation
		 */
		function getReturnedApproval() {
			const billingToken = urlParams.get( 'ba_token' );
			if ( billingToken ) {
				return { billingToken };
			}
			const orderId = urlParams.get( 'token' );
			const payerId = urlParams.get( 'PayerID' );
			if ( orderId && payerId ) {
				return { orderId, payerId };
			}
			return null;
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

			const returnedApproval = getReturnedApproval();
			if ( returnedApproval ) {
				// Back from the redirect
				appState.setLoading( true );
				submitApproved( returnedApproval );
			} else if ( isZeroClick() ) {
				// The donor chose PayPal before arriving (banner or email), so go straight to PayPal
				appState.setLoading( true );
				startSession( 'redirect' );
				return;
			}
			ready.value = true;
			await whenLabelDrawn( paypalButton.value );
			ctx.emit( 'ready' );
		} );

		function startPayPal() {
			if ( donorDetailsValid() ) {
				// Popup, or a modal if the browser blocks the popup
				startSession( 'auto' );
			}
		}

		return {
			ready,
			paypalButton,
			startPayPal
		};
	}
} );
</script>
