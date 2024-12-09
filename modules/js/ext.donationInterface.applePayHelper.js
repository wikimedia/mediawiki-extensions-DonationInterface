/**
 * Core functionality for DonationInterface forms
 *
 * @param $
 * @param mw
 */
( function ( $, mw ) {
	var di = mw.donationInterface; // Defined in ext.donationInterface.validation.js

	/**
	 * Try to obtain the "best" name from the available contact info sent back by Apple pay
	 *
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentrequest/2216120-requiredbillingcontactfields
	 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentcontact
	 * @param extraData
	 * @param billingContact
	 * @param shippingContact
	 * @return {*}
	 */
	function getBestApplePayContactName(
		extraData,
		billingContact,
		shippingContact
	) {
		var first_name, last_name;

		if (
		billingContact &&
		billingContact.givenName &&
		billingContact.givenName.length > 1
		) {
		first_name = billingContact.givenName;
		if ( billingContact.familyName && billingContact.familyName.length > 1 ) {
			last_name = billingContact.familyName;
		}
		}

		if ( first_name && !last_name ) {
		// suspected 'dad' scenario so use shipping contact
		if (
			shippingContact &&
			shippingContact.givenName &&
			shippingContact.givenName.length > 1
		) {
			first_name = shippingContact.givenName;
			if (
			shippingContact.familyName &&
			shippingContact.familyName.length > 1
			) {
			last_name = shippingContact.familyName;
			}
		}
		}

		extraData.first_name = first_name;
		extraData.last_name = last_name;
		return extraData;
	}
	// FIXME: move function declarations into object
	di.forms.apple = {
		getBestApplePayContactName: getBestApplePayContactName
	};
} )( jQuery, mediaWiki );
