(function ($, mw) {
	var myDeviceData;

	$('.submethods').before('<div id="paypal-button"></div>');

	// Create a client.
	braintree.client.create({
		authorization: mw.config.get('clientToken')
	}).then(function (clientInstance) {
		// TODO: myDeviceData will supply device data for non-recurring vault trxns
		// see https://developer.paypal.com/braintree/docs/guides/paypal/vault#collecting-device-data
		myDeviceData = clientInstance.deviceData;
		// Create a PayPal Checkout component.
		return braintree.paypalCheckout.create({
			client: clientInstance
		});
	}).then(function (paypalCheckoutInstance) {
		return paypalCheckoutInstance.loadPayPalSDK({
			vault: true
		});
	}).then(function (paypalCheckoutInstance) {
		return paypal.Buttons({
			fundingSource: paypal.FUNDING.PAYPAL,

			createBillingAgreement: function () {
				return paypalCheckoutInstance.createPayment({
					flow: 'vault', // Required
				});
			},

			onApprove: function (data, actions) {
				return paypalCheckoutInstance.tokenizePayment(data).then(function (payload) {
					// Submit `payload.nonce` to your server
					console.log("Approved. Now submit `payload.nonce` to your server")
					console.log(payload)

					// REFACTOR ME: Temp delayed redirect for simulation of complete process
					tyUrl = mw.config.get( 'wgDonationInterfaceThankYouPage' )
					setTimeout(function(){
						document.location.replace( tyUrl );
					}, 2000);

				});
			},

			onCancel: function (data) {
				console.log('PayPal payment canceled', JSON.stringify(data, 0, 2));
			},

			onError: function (err) {
				console.error('PayPal error', err);
			}
		}).render('#paypal-button');
	}).then(function () {
		// The PayPal button will be rendered in an html element with the ID
		// `paypal-button`. This function will be called when the PayPal button
		// is set up and ready to be used
	}).catch(function (err) {
		// Handle component creation error
		console.log(err)
	});

})(jQuery, mediaWiki);
