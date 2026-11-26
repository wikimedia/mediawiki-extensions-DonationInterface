/*global braintree:true, paypal:true*/
( function ( $, mw ) {
	const { hasValidWalletName, hasValidWalletEmail, showWalletFallbackFields } = require( 'ext.donationInterface.walletFallback' );
	/* Paypal iFrame comes with a zIndex of 100. Inorder to stack the autocomplete
	menu above the Paypal button, we need to set the zIndex to be at least a step higher.
	The mediawiki library for the menu item sets the autocomplete menu zIndex to 1 step higher
	than the parents zIndex here: resources/lib/jquery.ui/jquery.ui.autocomplete.js::484
	 */
	$( '#employer' ).css( { position: 'relative', zIndex: 100 } );

	let di = mw.donationInterface,
		myDeviceData,
		payment_method = $( '#payment_method' ).val(),
		scripts = mw.config.get( 'scriptsToLoad' );

	$( '.submethods' ).before( '<div id="' + payment_method + '-button"></div>' );

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			const failUrl = new URL( mw.config.get( 'DonationInterfaceFailUrl' ), location.href );
			if ( result.orderId ) {
				failUrl.searchParams.set( 'order_id', result.orderId );
			}
			document.location.replace( failUrl.toString() );
		} else if ( mw.monthlyConvert && mw.monthlyConvert.canShowModal() ) {
			mw.monthlyConvert.init();
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	function showClientSideErrorMessage( msg ) {
		$( '.errorMsg' ).remove();
		$( '#errorReference' ).html( '' );
		$( '#topError' ).html( msg );
	}

	function getPaymentMethodUsage() {
		return $( '#recurring' ).val() === '1' ? 'multi_use' : 'single_use';
	}

	function getAmountValue() {
		return $.trim( $( '#amount' ).val() );
	}

	function createBraintreeClient() {
		return braintree.client.create( {
			authorization: mw.config.get( 'clientToken' )
		} );
	}

	function createVenmoInstance( braintreeClientInstance ) {
		return braintree.venmo.create( getVenmoCreateOptions( braintreeClientInstance ) );
	}

	function getVenmoCreateOptions( braintreeClientInstance ) {
		return {
			client: braintreeClientInstance,
			allowDesktop: true,
			mobileWebFallBack: true,
			allowNewBrowserTab: true,
			allowDesktopWebLogin: false, // true to force web login, hide QR code
			paymentMethodUsage: getPaymentMethodUsage(),
			totalAmount: getAmountValue()
		};
	}

	function buildVenmoSendData( payload ) {
		const sendData = {
			payment_token: payload.nonce,
			device_data: myDeviceData,
			user_name: payload.details.username,
			gateway_session_id: payload.details.paymentContextId
		};

		// payload.details.payerInfo is undefined for non-us sandbox account
		if ( payload.details.payerInfo ) {
			sendData.first_name = payload.details.payerInfo.firstName;
			sendData.last_name = payload.details.payerInfo.lastName;
			sendData.phone = payload.details.payerInfo.phoneNumber;
			sendData.email = payload.details.payerInfo.email;
			sendData.street_address = payload.details.payerInfo.shippingAddress;
			sendData.customer_id = payload.details.payerInfo.externalId;
		}

		return sendData;
	}

	function handleVenmoError( err ) {
		if ( err && err.code === 'VENMO_CANCELED' ) {
			showClientSideErrorMessage( 'App is not available or user aborted payment flow' );
		} else if ( err && err.code === 'VENMO_APP_CANCELED' ) {
			showClientSideErrorMessage( 'User canceled payment flow' );
		} else {
			showClientSideErrorMessage( err && err.message ? err.message : String( err ) );
		}
	}

	function handlePayPalError( err ) {
		showClientSideErrorMessage( 'PayPal error: ' + ( err && err.message ? err.message : String( err ) ) );
	}

	function handleVenmoSuccess( payload ) {
		const sendData = buildVenmoSendData( payload );
		if ( !hasValidWalletName( sendData.first_name ) ||
			!hasValidWalletName( sendData.last_name ) ||
			!hasValidWalletEmail( sendData.email ) ) {
			showWalletFallbackFields(
				$( '#venmo-button' ),
				sendData.email,
				( firstName, lastName, email ) => {
					sendData.first_name = firstName;
					sendData.last_name = lastName;
					sendData.email = email;
					di.forms.callDonateApi( handleApiResult, sendData, 'di_donate_braintree' );
				}
			);
		} else {
			di.forms.callDonateApi( handleApiResult, sendData, 'di_donate_braintree' );
		}
	}

	function displayVenmoButton( venmoInstance ) {
		const venmoButton = document.getElementById( 'venmo-button' );
		if ( !venmoButton ) {
			showClientSideErrorMessage( 'Venmo button element not found' );
			return;
		}

		// On iOS, after Venmo app approval the OS may redirect back to the default
		// browser (e.g. Chrome) instead of the originating browser (e.g. Safari).
		// The tokenization result is encoded in the URL, so whichever browser loads
		// this page can detect and process it automatically — no user action needed.
		if ( venmoInstance.hasTokenizationResult() ) {
			venmoInstance.tokenize()
				.then( handleVenmoSuccess )
				.catch( handleVenmoError );
			return;
		}

		venmoButton.style.display = 'block';
		venmoButton.addEventListener( 'click', () => {
			venmoButton.disabled = true;

			venmoInstance.tokenize()
				.then( ( payload ) => {
					if ( payload ) {
						handleVenmoSuccess( payload );
					}
				} )
				.catch( handleVenmoError )
				.then( () => {
					venmoButton.removeAttribute( 'disabled' );
				} );
		} );
	}

	// myDeviceData will supply device data for non-recurring vault trxns
	// see https://developer.paypal.com/braintree/docs/guides/paypal/vault#collecting-device-data
	// https://developer.paypal.com/braintree/docs/guides/premium-fraud-management-tools/device-data-collection/javascript/v3/#collecting-device-data
	function getDeviceData( braintreeClientInstance ) {
		braintree.dataCollector.create( {
			client: braintreeClientInstance
		} ).then( ( dataCollectorInstance ) => {
			// At this point, you should access the dataCollectorInstance.deviceData value and provide it
			// to your server, e.g. by injecting it into your form as a hidden input
			myDeviceData = dataCollectorInstance.deviceData;
		} ).catch( ( err ) => {
			showClientSideErrorMessage( 'Device data error' + err );
		} );
	}

	function setup() {
		const isRecurring = !!$( '#recurring' ).val();
		if ( payment_method === 'paypal' ) {
			createBraintreeClient().then( ( braintreeClientInstance ) => {
				getDeviceData( braintreeClientInstance );
				// Create a PayPal Checkout component.
				return braintree.paypalCheckoutV6.create( {
					client: braintreeClientInstance
				} );
			} ).then( ( paypalCheckoutInstance ) => paypalCheckoutInstance.loadPayPalSDK( {
				vault: isRecurring
			} ) ).then( ( paypalCheckoutInstance ) => {
				// Added to cancelUrl, so processDonorReturn() knows the donor cancelled at PayPal
				const CANCEL_PARAM = 'paypalCancel';
				const returnUrl = new URL( mw.config.get( 'DonationInterfaceReturnUrl' ) );
				const cancelUrl = new URL( returnUrl );
				cancelUrl.searchParams.set( CANCEL_PARAM, '1' );
				const onApprove = function ( data ) {
					return paypalCheckoutInstance.tokenizePayment( data ).then( ( payload ) => {
						const sendData = {
							payment_token: payload.nonce,
							device_data: myDeviceData,
							first_name: payload.details.firstName,
							last_name: payload.details.lastName,
							email: payload.details.email,
							street_address: payload.details.shippingAddress
						};

						if ( !hasValidWalletName( sendData.first_name ) ||
							!hasValidWalletName( sendData.last_name ) ||
							!hasValidWalletEmail( sendData.email ) ) {
							showWalletFallbackFields(
								$( '#paypal-button' ),
								sendData.email,
								( firstName, lastName, email ) => {
									sendData.first_name = firstName;
									sendData.last_name = lastName;
									sendData.email = email;
									di.forms.callDonateApi( handleApiResult, sendData, 'di_donate_braintree' );
								}
							);
						} else {
							di.forms.callDonateApi( handleApiResult, sendData, 'di_donate_braintree' );
						}
					} );
				};
				let session;
				if ( isRecurring ) {
					session = paypalCheckoutInstance.createBillingAgreementSession( {
						amount: getAmountValue(),
						billingAgreementDescription: 'Wikimedia Foundation',
						contactPreference: 'UPDATE_CONTACT_INFO',
						currency: $( '#currency' ).val(),
						planType: 'RECURRING',
						returnUrl: returnUrl.toString(),
						cancelUrl: cancelUrl.toString(),
						onApprove: onApprove,
						onError: handlePayPalError
					} );
				} else {
					session = paypalCheckoutInstance.createOneTimePaymentSession( {
						amount: getAmountValue(),
						commit: true,
						contactPreference: 'UPDATE_CONTACT_INFO',
						currency: $( '#currency' ).val(),
						intent: 'authorize',
						offerCredit: false,
						returnUrl: returnUrl.toString(),
						cancelUrl: cancelUrl.toString(),
						onApprove: onApprove,
						onError: handlePayPalError
					} );
				}
				const urlParams = ( new URL( location.href ) ).searchParams;
				const paymentToken = urlParams.get( 'token' );
				const billingToken = urlParams.get( 'ba_token' );
				if ( billingToken ) {
					// PayPal sends no PayerID back for a billing agreement. Only pass
					// billingToken: with paymentId as well, tokenizePayment() treats it
					// as checkout with vault, which needs a payerId.
					return onApprove( { billingToken: billingToken } );
				} else if ( paymentToken ) {
					return onApprove( {
						paymentId: paymentToken,
						payerId: urlParams.get( 'PayerID' )
					} );
				}
				return session.start( { presentationMode: 'redirect' } );
			} ).catch( handlePayPalError );
		} else if ( payment_method === 'venmo' ) {
			createBraintreeClient().then( ( braintreeClientInstance ) => {
				getDeviceData( braintreeClientInstance );
				return createVenmoInstance( braintreeClientInstance );
			} ).then( ( venmoInstance ) => {
				if ( !venmoInstance.isBrowserSupported() ) {
					showClientSideErrorMessage( 'Browser does not support Venmo' );
					return;
				}
				displayVenmoButton( venmoInstance );
			} ).catch( ( err ) => {
				showClientSideErrorMessage( 'Error creating Venmo:' + err );
			} );
		}
	}

	// loadScript (called internally) will display an error message when a
	// script fails to load, so we just suppress any rejections with an
	// empty catch block here.
	mw.donationInterface.forms.loadScriptsInOrder( scripts )
		.then( setup )
		.catch( () => {} );

} )( jQuery, mediaWiki );
