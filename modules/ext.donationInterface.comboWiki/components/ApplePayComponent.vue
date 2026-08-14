<template>
	<div id="applepay-container">
		<apple-pay-button
			v-if="showApplePayButtonFlag"
			id="applepay-btn"
			ref="applePayButtonElementRef"
			class="button"
			buttonstyle="black"
			type="donate"
			:locale="locale"
			@click="handleApplePaySubmitClick"
		></apple-pay-button>
	</div>
</template>

<script>
/* global ApplePaySession ApplePayError */

const { defineComponent, onBeforeUnmount, onMounted, ref } = require( 'vue' );
module.exports = exports = defineComponent( {
	name: 'GravyApplePay',
	compilerOptions: {
		// remove warning from the console due to non-vue apply pay button element
		// https://vuejs.org/api/application.html#app-config-compileroptions
		isCustomElement: ( tag ) => tag === 'apple-pay-button'
	},

	props: {
		donation: {
			type: Object,
			required: true
		}
	},
	emits: [ 'presubmit', 'submit', 'error' ],
	setup( props, ctx ) {
		let appleSession = null;
		let extraData = {};
		let appleScriptSrc = null;

		const applePayButtonElementRef = ref();
		const showApplePayButtonFlag = ref( false );
		const applePayPaySessionVersionNumber = 3; // https://developer.apple.com/documentation/apple_pay_on_the_web/apple_pay_on_the_web_version_history

		onMounted( () => {
			const config = mw.config.get( 'gravyConfiguration' );
			appleScriptSrc = config.appleScript;
			mw.donationInterface.forms.loadScript( appleScriptSrc )
				.then( setupApplePayForm )
				.catch( ( e ) => {
					mw.log.error( 'combowiki applepay load failed', e );
				} );
		} );

		onBeforeUnmount( () => {
			// Abort any in-flight Apple Pay session and detach its handlers
			if ( appleSession ) {
				appleSession.oncancel = null;
				appleSession.onvalidatemerchant = null;
				appleSession.onpaymentauthorized = null;
				try {
					appleSession.abort();
				} catch ( e ) {
					mw.log.error( 'combowiki applepay unload failed', e );
				}
				appleSession = null;
			}
			extraData = {};
			showApplePayButtonFlag.value = false;

			// Remove the Apple Pay script this component injected into the page
			if ( appleScriptSrc ) {
				const applePayElements = document.body.querySelectorAll( 'script[src="' + appleScriptSrc + '"]' );
				for ( const node of applePayElements ) {
					node.remove();
				}
				appleScriptSrc = null;
			}
		} );

		function clearApplePaySessionAndEnableButton() {
			appleSession = null;
			if ( applePayButtonElementRef.value ) {
				applePayButtonElementRef.value.disabled = false;
			}
		}
		function setupApplePayForm() {
			// Check apple pay availability before showing button
			if ( window.ApplePaySession ) {
				showApplePayButtonFlag.value = true;
			} else {
				mw.donationInterface.validation.showErrors( {
					general: mw.message(
						'donate_interface-error-msg-apple_pay_unsupported',
						mw.config.get( 'DonationInterfaceOtherWaysURL' )
					).plain()
				} );
				mw.donationInterface.forms.addDebugMessage( 'Apple Pay failure: Unable to find ApplePaySession in browser' );
			}
		}

		function handleApplePaySubmitClick( e ) {
			e.preventDefault();
			const button = e.currentTarget;

			// Prevent double-tap / double session
			if ( appleSession || button.disabled ) {
				return;
			}

			button.disabled = true;
			setupApplePaySession();
			appleSession.begin();
		}

		function handleSuccessfulSubmitPaymentResult() {
			appleSession.completePayment( {
				status: ApplePaySession.STATUS_SUCCESS
			} );
			appleSession = null;
		}
		function handleFailedPaymentResult( applePayErrors ) {
			appleSession.completePayment( {
				status: ApplePaySession.STATUS_FAILURE,
				errors: applePayErrors
			} );
		}

		function onValidationSuccess( data ) {
			appleSession.completeMerchantValidation( data.session );
		}

		function onValidationFailure() {
			appleSession.abort();
			clearApplePaySessionAndEnableButton();
		}

		function validateApplePayPaymentSession() {
			return function ( event ) {
				ctx.emit( 'presubmit', event, onValidationSuccess, onValidationFailure );
			};
		}

		function setupApplePaySession() {
			const paymentRequestObject = {
				countryCode: props.donation.country,
				currencyCode: props.donation.currency,
				merchantCapabilities: [ 'supportsCredit', 'supportsDebit', 'supports3DS' ],
				supportedNetworks: [ 'visa', 'masterCard', 'amex', 'discover' ],
				requiredBillingContactFields: [ 'email', 'name', 'phone', 'postalAddress' ],
				requiredShippingContactFields: [ 'email', 'name' ],
				total: {
					label: 'Wikimedia Foundation',
					type: 'final',
					amount: props.donation.amount
				}
			};
			appleSession = new ApplePaySession( applePayPaySessionVersionNumber, paymentRequestObject );
			appleSession.oncancel = function () {
				clearApplePaySessionAndEnableButton();
			};
			appleSession.onvalidatemerchant = validateApplePayPaymentSession( appleSession );

			appleSession.onpaymentauthorized = function ( event ) {
				const bContact = event.payment.billingContact,
					sContact = event.payment.shippingContact;
				let paymentSubmethod = event.payment.token.paymentMethod.network;
				if ( !paymentSubmethod ) {
					paymentSubmethod = '';
				}
				extraData = mw.donationInterface.forms.apple.getBestApplePayContactName( extraData, bContact, sContact );
				extraData.postal_code = bContact.postalCode;
				extraData.state_province = bContact.administrativeArea;
				extraData.city = bContact.locality;
				if ( Array.isArray( bContact.addressLines ) && bContact.addressLines.length > 0 ) {
					extraData.street_address = bContact.addressLines[ 0 ];
				}
				extraData.email = sContact.emailAddress;
				extraData.payment_submethod = paymentSubmethod.toLowerCase();
				extraData.payment_token = JSON.stringify( event.payment.token );

				const applePayErrors = getApplePayErrors();
				if ( applePayErrors.length === 0 ) {
					ctx.emit( 'submit', extraData, handleSuccessfulSubmitPaymentResult, handleFailedPaymentResult );
				} else {
					// First and last name not configured in ApplePay sheet.
					// Show errors in payment sheet
					handleFailedPaymentResult( applePayErrors );
					ctx.emit( 'error', applePayErrors );
				}
			};
		}

		function getApplePayErrors() {
			const applePayErrors = [];
			if ( !extraData.first_name || !extraData.first_name.trim() ) {
				applePayErrors.push( new ApplePayError(
					'billingContactInvalid',
					'name',
					mw.msg( 'donate_interface-error-msg-first_name' )
				) );
			}
			if ( !extraData.last_name || !extraData.last_name.trim() ) {
				applePayErrors.push( new ApplePayError(
					'billingContactInvalid',
					'name',
					mw.msg( 'donate_interface-error-msg-last_name' )
				) );
			}
			if ( !extraData.email || !extraData.email.trim() ) {
				applePayErrors.push( new ApplePayError(
					'shippingContactInvalid',
					'email',
					mw.msg( 'donate_interface-error-msg-email' )
				) );
			}
			return applePayErrors;
		}

		return {
			locale: 'en',
			applePayButtonElementRef,
			showApplePayButtonFlag,
			handleApplePaySubmitClick
		};
	},

	computed: {}
} );
</script>
