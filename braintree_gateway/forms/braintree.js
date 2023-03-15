/*global braintree:true, paypal:true*/
( function ( $, mw ) {
	/* Paypal iFrame comes with a zIndex of 100. Inorder to stack the autocomplete
	menu above the Paypal button, we need to set the zIndex to be at least a step higher.
	The mediawiki library for the menu item sets the autocomplete menu zIndex to 1 step higher
	than the parents zIndex here: resources/lib/jquery.ui/jquery.ui.autocomplete.js::484
	 */
	$( '#employer' ).css( { position: 'relative', zIndex: 100 } );

	var di = mw.donationInterface,
		myDeviceData;

	$( '.submethods' ).before( '<div id="paypal-button"></div>' );

	function handleApiResult( result ) {
		if ( result.isFailed ) {
			document.location.replace( mw.config.get( 'DonationInterfaceFailUrl' ) );
		} else {
			document.location.replace( mw.config.get( 'DonationInterfaceThankYouPage' ) );
		}
	}

	// Create a client.
	braintree.client.create( {
		authorization: mw.config.get( 'clientToken' )
	} ).then( function ( clientInstance ) {
		// TODO: myDeviceData will supply device data for non-recurring vault trxns
		// see https://developer.paypal.com/braintree/docs/guides/paypal/vault#collecting-device-data
		myDeviceData = clientInstance.deviceData;
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
						payment_token: payload.nonce
					};

					di.forms.callDonateApi(
						handleApiResult, sendData, 'di_donate_braintree'
					);
				} );
			},

			onCancel: function ( data ) {
				// console.log( 'PayPal payment canceled', JSON.stringify( data, 0, 2 ) );
			},

			onError: function ( err ) {
				// console.error( 'PayPal error', err );
			}
		} ).render( '#paypal-button' );
	} ).then( function () {
		// The PayPal button will be rendered in an html element with the ID
		// `paypal-button`. This function will be called when the PayPal button
		// is set up and ready to be used
	} ).catch( function ( err ) {
		// Handle component creation error
		// console.log( err );
	} );

} )( jQuery, mediaWiki );
