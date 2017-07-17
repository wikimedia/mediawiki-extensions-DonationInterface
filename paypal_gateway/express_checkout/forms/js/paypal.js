( function ( $, mw ) {
	var di = mw.donationInterface;

	function redirect( result ) {
		// We don't actually want to enable the form on redirect or in the
		// complete phase of callDonateApi, so we override enable here.
		di.forms.enable = function(){};
		top.location.href = result.formaction;
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( redirect );
	};
	if ( !di.validation.hasErrors() ) {
		di.forms.submit();
	}
} )( jQuery, mediaWiki );
