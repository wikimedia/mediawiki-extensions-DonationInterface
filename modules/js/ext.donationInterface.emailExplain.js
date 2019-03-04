( function ( $, mw ) {
	$( function () {
		var emailRow = $( '#email' ).closest( 'tr' ),
			message = mw.msg( 'donate_interface-email-explain' ),
			optInValue = mw.donationInterface.forms.getOptIn();

		function showMessage() {
			emailRow.after( '<tr id="email_explain"><td>' + message + '</td></tr>' );
		}

		if ( optInValue === '0' ) {
			showMessage();
		}

	} );
} )( jQuery, mediaWiki );
