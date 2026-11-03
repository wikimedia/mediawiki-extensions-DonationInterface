<template>
	<div
		id="applepay-container"
	>
	</div>
</template>

<script>
/* global ApplePayError */

const { defineComponent, onBeforeUnmount, onMounted, inject, toRaw, watch } = require( 'vue' );
const { getAdyenCheckout, getAdyenApplePayConfiguration, amountInMinorUnits } = require( '../utils.js' );

module.exports = exports = defineComponent( {
	name: 'AdyenApplePay',
	inject: [ 'params' ],

	props: {
		donation: {
			type: Object,
			required: true
		}
	},
	emits: [ 'presubmit', 'submit', 'error' ],
	setup( props, ctx ) {
		const params = inject( 'params' );
		let applePayComponent;
		let extraData = {};

		function handleSuccessfulSubmitPaymentResult( actions ) {
			return ( result ) => {
				if ( !result ) {
					actions.reject();
					return;
				}
				const response = result.result;
				if ( response.isFailed ) {
					actions.reject();
				}  else {
					actions.resolve( { resultCode: 'Authorised' } );
				}
			};
		}
		function handleFailedPaymentResult( actions ) {
			return ( applePayErrors ) => {
				ctx.emit( 'error', applePayErrors );
				actions.reject( applePayErrors );
			};
		}

		function handleSubmit( state, component, actions ) {
			if ( !state.isValid ) {
				handleError( '' );
				actions.reject();
				return;
			}

			const paymentMethod = state.data ? state.data.paymentMethod : null;

			if ( !paymentMethod ) {
				handleError( 'adyen-payment-method-missing' );
				actions.reject();
				return;
			}

			extraData = Object.assign( {}, extraData, { payment_token: state.data.paymentMethod.applePayToken } );

			ctx.emit( 'submit', extraData, handleSuccessfulSubmitPaymentResult( actions ), handleFailedPaymentResult( actions ) );
		}
		function handleError( error ) {
			// Ignore blank errors, matching the existing Adyen integration.
			if ( typeof error.error === 'string' && error.error === '' ) {
				return;
			}
			// Send error on donor-initiated cancellations
			if ( error && error.cause && error.cause.statusCode === 'CANCELED' ) {
				location.assign( mw.config.get( 'DonationInterfaceCancelUrl' ) );
				return;
			}
			// The donor closed the Apple Pay sheet. Adyen reports that as a CANCEL error
			if ( error && error.name === 'CANCEL' ) {
				return;
			}
			if ( typeof error.error === 'string' && error.error.slice( 0, 8 ) === 'error.va' ) {
				// T349600 Log validation error codes only if sf-cc-num.02 donate_interface-error-msg-card-number-do-not-match-card-brand)
				// with date time and time zone for adyen to investigate their card validation js issue
				if ( error.error === 'error.va.sf-cc-num.02' ) {
					error.error = 'Adyen error: ' + error.error + ' on ' + new Date().toString();
				} else {
					return;
				}
			} else {
				// handle component error
				ctx.emit( 'error', {
					type: 'validation',
					messages: [ mw.message(
						'donate_interface-error-msg-general',
						params.DonationInterfaceOtherWaysURL
					).plain() ]
				} );
			}
			ctx.emit( 'error', error );
		}

		function onValidationSuccess( resolve ) {
			return ( data ) => {
				resolve( data.session );
			};
		}

		function onValidationFailure( reject ) {
			return ( errors ) => {
				if ( errors ) {
					ctx.emit( 'error', errors );
				}
				reject();
			};
		}

		function validateApplePayPaymentSession() {
			return function ( resolve, reject, validationUrl ) {
				ctx.emit( 'presubmit', {
					validationURL: validationUrl
				}, onValidationSuccess( resolve ), onValidationFailure( reject ) );
			};
		}

		function handleAuthorized( data, actions ) {
			// Required here rather than at the top so the jest tests don't need to resolve
			// the ResourceLoader module, which is a dependency of ext.donationInterface.comboWiki
			const { getBestApplePayContactName, mapAppleNetworkToSubmethod } = require( 'ext.donationInterface.applePayHelper' );
			const event = data.authorizedEvent,
				bContact = event.payment.billingContact,
				sContact = event.payment.shippingContact,
				temp = getBestApplePayContactName( extraData, bContact, sContact );
			temp.postal_code = bContact.postalCode;
			temp.state_province = bContact.administrativeArea;
			temp.city = bContact.locality;
			if ( bContact && Array.isArray( bContact.addressLines ) && bContact.addressLines.length > 0 ) {
				temp.street_address = bContact.addressLines[ 0 ];
			}
			temp.email = sContact.emailAddress;
			temp.payment_submethod = mapAppleNetworkToSubmethod( event.payment.token.paymentMethod.network );
			extraData = Object.assign( {}, extraData, temp );

			// Let Adyen go on to onSubmit. The payment sheet stays open until
			// onSubmit's actions are settled, after our donate API call.
			const applePayErrors = getApplePayErrors();
			if ( applePayErrors.length === 0 ) {
				actions.resolve();
			} else {
				// First and last name not configured in ApplePay sheet.
				// Show errors in payment sheet
				handleFailedPaymentResult( applePayErrors );
				actions.reject();
				ctx.emit( 'error', applePayErrors );
			}
		}

		onMounted( async () => {
			const adyenConfiguration = toRaw( params.adyenConfiguration );

			if ( !adyenConfiguration ) {
				ctx.emit( 'error', 'adyen-configuration-missing' );
				return;
			}

			await setupAdyen( adyenConfiguration );
		} );

		async function setupAdyen( adyenConfiguration ) {
			try {
				const donation = toRaw( props.donation );
				// The same checkout and config PaymentMethodForm's readiness check used
				const checkout = await getAdyenCheckout( adyenConfiguration, donation.country );
				const config = Object.assign(
					getAdyenApplePayConfiguration( donation ),
					{
						amount: {
							currency: donation.currency,
							value: amountInMinorUnits( donation.amount, donation.currency )
						},
						buttonColor: 'white-outline',
						buttonType: 'donate',
						onSubmit: handleSubmit,
						onError: handleError,
						onValidateMerchant: validateApplePayPaymentSession(),
						onAuthorized: handleAuthorized
					}
				);
				applePayComponent = new window.AdyenWeb.ApplePay( checkout, config );
				// Asked again in case the answer changed since PaymentMethodForm's check
				await applePayComponent.isAvailable();

				applePayComponent.mount( '#applepay-container' );

			} catch ( error ) {
				mw.log.error( 'combowiki applepay unload failed', error );
				emitUnavailable();
			}
		}
		watch(
			() => [ props.donation.amount, props.donation.currency ],
			( [ amount, currency ] ) => {
				if ( !applePayComponent ) {
					return;
				}
				applePayComponent.update( {
					amount: { currency, value: amountInMinorUnits( amount, currency ) }
				} );
			}
		);
		onBeforeUnmount( () => {
			extraData = {};
			if ( applePayComponent ) {
				applePayComponent.unmount();
				applePayComponent = null;
			}
		} );

		function emitUnavailable() {
			ctx.emit( 'error', {
				type: 'validation',
				messages: [ mw.message(
					'donate_interface-error-msg-apple_pay_unsupported',
					params.DonationInterfaceOtherWaysURL
				).plain() ]
			} );
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
	}
} );

</script>
