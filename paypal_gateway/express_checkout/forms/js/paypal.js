( function ( $, mw ) {
	var di = mw.donationInterface;

	function redirect( result ) {
		top.location.href = result.formaction;
	}

	di.forms.submit = function () {
		di.forms.callDonateApi( redirect );
	};
	di.forms.submit();
} )( jQuery, mediaWiki );
