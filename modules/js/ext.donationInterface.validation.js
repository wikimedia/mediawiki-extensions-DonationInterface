/**
 * Client-side validation logic for DonationInterface
 * For starters, we just redirect to the existing global functions.
 * They should be rewritten here when we modernize the remaining forms.
 */
( function ( $, mw ) {
	var di = mw.donationInterface = {};

	di.validation = {
		validateAmount: window.validateAmount,
		validatePersonal: window.validate_personal
	};
} )( jQuery, mediaWiki );
