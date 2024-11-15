( function ( $, mw ) {

	var $linkButton = $( 'button.send-new-link' ),
		urlParams = new URLSearchParams( window.location.search );

	function requestNewChecksumLink( contactID, page, subpage ) {
		var api = new mw.Api(),
			params = {
				contactID: contactID,
				action: 'requestNewChecksumLink',
				page: page
			};
		if ( subpage ) {
			params.subpage = subpage;
		}
		api.post( params );
	}
	mw.donationInterface = mw.donationInterface || {};
	mw.donationInterface.requestNewChecksumLink = requestNewChecksumLink;
	if ( mw.config.get( 'showRequestNewChecksumModal' ) ) {
		$( '.link-modal-screen' ).show();
		$linkButton.click( function ( e ) {
			mw.donationInterface.requestNewChecksumLink(
				urlParams.get( 'contact_id' ),
				mw.config.get( 'requestNewChecksumPage' ),
				mw.config.get( 'requestNewChecksumSubpage' )
			);
			$linkButton.attr( 'disabled', 'disabled' );
			$( 'p.link-sent' ).show();
		} );
	}
} )( jQuery, mediaWiki );
