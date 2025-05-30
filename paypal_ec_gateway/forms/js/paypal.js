( function ( $, mw ) {
	let di = mw.donationInterface,
		rules = mediaWiki.config.get( 'wgDonationInterfaceValidationRules' ) || [],
		rulesSatisfied = false;

	function redirect( result ) {
		// We don't actually want to enable the form on redirect or in the
		// complete phase of callDonateApi, so we override enable here.
		di.forms.enable = function () {};
		location.assign( result.redirect );
	}

	di.forms.submit = function () {
		// MediaWiki uses the "uselang" parameter to set the language for localization
		// Checkout /payments/includes/api/ApiMain.php
		di.forms.callDonateApi( redirect, { uselang: $( '#language' ).val() } );
	};
	if ( rules.length === 0 ) {
		rulesSatisfied = true;
	} else {
		// TODO: refactor validation to separate out showing errors, and
		// check that all the rules are satisfied on page load without
		// showing errors.
		if ( di.forms.getOptIn() !== '' ) {
			rulesSatisfied = true;
		}
	}
	if ( rulesSatisfied && !di.validation.hasErrors() ) {
		di.forms.submit();
	}
} )( jQuery, mediaWiki );
