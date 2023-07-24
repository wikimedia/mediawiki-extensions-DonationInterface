/*global braintree:true, paypal:true*/
( function ( $, mw ) {
	/* Paypal iFrame comes with a zIndex of 100. Inorder to stack the autocomplete
	menu above the Paypal button, we need to set the zIndex to be at least a step higher.
	The mediawiki library for the menu item sets the autocomplete menu zIndex to 1 step higher
	than the parents zIndex here: resources/lib/jquery.ui/jquery.ui.autocomplete.js::484
	 */
	$( '#employer' ).css( { position: 'relative', zIndex: 100 } );

	var di = mw.donationInterface,
		myDeviceData,
		payment_method = $( '#payment_method' ).val();

	$( '.submethods' ).before( '<div id="' + payment_method + '-button"></div>' );

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			document.location.replace( mw.config.get( 'DonationInterfaceFailUrl' ) );
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	function clearAllErrorMessages() {
		$( '.error-msg' ).remove();
		$( '#topError' ).html( '' );
		$( '#errorReference' ).html( '' );
	}

	// render error Message to client
	function showClientSideErrorMessage( msg ) {
		clearAllErrorMessages();
		$( '.submethods' ).before( '<p class="error-msg">' + msg + '</p>' );
	}

	// Create a client.
	if ( payment_method === 'paypal' ) {
		braintree.client.create( {
			authorization: mw.config.get( 'clientToken' )
		} ).then( function ( clientInstance ) {
			// TODO: myDeviceData will supply device data for non-recurring vault trxns
			// see https://developer.paypal.com/braintree/docs/guides/paypal/vault#collecting-device-data
			// myDeviceData = clientInstance.deviceData;
			// Create a PayPal Checkout component.
			return braintree.paypalCheckout.create( {
				client: clientInstance
			} );
		} ).then( function ( paypalCheckoutInstance ) {
			return paypalCheckoutInstance.loadPayPalSDK( {
				vault: true
			} );
		} ).then( function ( paypalCheckoutInstance ) {
			return paypal.Buttons( {
				fundingSource: paypal.FUNDING.PAYPAL,

				createBillingAgreement: function () {
					return paypalCheckoutInstance.createPayment( {
						flow: 'vault' // Required
					} );
				},

				onApprove: function ( data, actions ) {
					return paypalCheckoutInstance.tokenizePayment( data ).then( function ( payload ) {
						var sendData = {
							payment_token: payload.nonce,
							device_data: myDeviceData
						};

						di.forms.callDonateApi(
							handleApiResult, sendData, 'di_donate_braintree'
						);
					} );
				},

				onCancel: function ( data ) {
					showClientSideErrorMessage( 'PayPal payment canceled' + JSON.stringify( data, 0, 2 ) );
				},

				onError: function ( err ) {
					showClientSideErrorMessage( 'PayPal error' + err );
				}
			} ).render( '#paypal-button' );
		} ).then( function () {
			// The PayPal button will be rendered in an html element with the ID
			// `paypal-button`. This function will be called when the PayPal button
			// is set up and ready to be used
		} ).catch( function ( err ) {
			// Handle component creation error
			showClientSideErrorMessage( 'component creation error: ' + err );
		} );
	}
	if ( payment_method === 'venmo' ) {
		var venmoButton = document.getElementById( 'venmo-button' );
		braintree.client.create( {
			authorization: mw.config.get( 'clientToken' )
		} ).then( function ( clientInstance ) {
			// Create a Venmo component.
			return braintree.venmo.create( {
				client: clientInstance,
				allowDesktop: true,
				mobileWebFallBack: true,
				allowNewBrowserTab: false,
				allowDesktopWebLogin: true, // force web login, QR code depreciate
				paymentMethodUsage: 'multi_use'
			} );
		} ).then( function ( venmoInstance ) {
			// Verify browser support before proceeding.
			if ( !venmoInstance.isBrowserSupported() ) {
				showClientSideErrorMessage( 'Browser does not support Venmo' );
				return;
			}
			function handleVenmoError( err ) {
				if ( err.code === 'VENMO_CANCELED' ) {
					showClientSideErrorMessage( 'App is not available or user aborted payment flow' );
				} else if ( err.code === 'VENMO_APP_CANCELED' ) {
					showClientSideErrorMessage( 'User canceled payment flow' );
				} else {
					showClientSideErrorMessage( 'An error occurred:' + err.message );
				}
			}
			function handleVenmoSuccess( payload ) {
				// todo: pass more data like payload.details T340799
				var sendData = {
					payment_token: payload.nonce,
					user_name: payload.details.username
				};

				di.forms.callDonateApi(
					handleApiResult, sendData, 'di_donate_braintree'
				);
			}
			function displayVenmoButton( venmoInstance ) {
				venmoButton.style.display = 'block';
				venmoButton.addEventListener( 'click', function () {
					venmoButton.disabled = true;
					venmoInstance.tokenize().then( handleVenmoSuccess ).catch( handleVenmoError ).then( function () {
						venmoButton.removeAttribute( 'disabled' );
					} );
				} );
			}
			displayVenmoButton( venmoInstance );
		} ).catch( function ( err ) {
			showClientSideErrorMessage( 'Error creating Venmo:' + err );
		} );
	}

} )( jQuery, mediaWiki );
