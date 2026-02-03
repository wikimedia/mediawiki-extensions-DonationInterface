/**
 * Apple Pay helper functions for DonationInterface forms
 * @param $ jQuery
 * @param mw mediaWiki
 */
( function ( $, mw ) {
	const di = mw.donationInterface; // Defined in ext.donationInterface.validation.js
	di.forms = di.forms || {};

	function isNotEmpty( string ) {
		return string && string.trim().length > 0;
	}

	function isLongerThan1Char( string ) {
		return string && string.trim().length > 1;
	}

	function hasBothNamesLongerThan1Char( contact ) {
		return contact && isLongerThan1Char( contact.givenName ) && isLongerThan1Char( contact.familyName );
	}

	function hasBothNames( contact ) {
		return contact && isNotEmpty( contact.givenName ) && isNotEmpty( contact.familyName );
	}

	di.forms.apple = {
		/*
		 * Try to obtain the "best" name from the available contact info sent back by Apple Pay
		 *
		 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentrequest/2216120-requiredbillingcontactfields
		 * @see https://developer.apple.com/documentation/apple_pay_on_the_web/applepaypaymentcontact
		 */
		getBestApplePayContactName: function (
			extraData,
			billingContact,
			shippingContact
		) {
			let preferredContact;

			// Since the Apple Pay sheet shows the name by the shipping contact selector,
			// we might want to check the shipping contact first, but in practice we were
			// getting a lot of low-quality names (e.g. 'Dad') from the shipping contact.
			// The billing contact seems to more consistently have real names so start there.
			if ( hasBothNamesLongerThan1Char( billingContact ) ) {
				preferredContact = billingContact;
			} else if ( hasBothNamesLongerThan1Char( shippingContact ) ) {
				preferredContact = shippingContact;
			} else if ( hasBothNames( billingContact ) ) {
				preferredContact = billingContact;
			} else if ( hasBothNames( shippingContact ) ) {
				preferredContact = shippingContact;
			}

			if ( preferredContact ) {
				extraData.first_name = preferredContact.givenName.trim();
				extraData.last_name = preferredContact.familyName.trim();
			}

			return extraData;
		}
	};
} )( jQuery, mediaWiki );
