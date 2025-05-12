( function ( $, mw ) {

	const $linkForm = $( 'form.send-new-link' );

	function requestNewChecksumLink( email, page, subpage ) {
		const api = new mw.Api(),
			params = {
				email: email,
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
		$linkForm.submit( ( e ) => {
			e.preventDefault();
			mw.donationInterface.requestNewChecksumLink(
				$( '#new-checksum-link-email' ).val(),
				mw.config.get( 'requestNewChecksumPage' ),
				mw.config.get( 'requestNewChecksumSubpage' )
			);
			$( 'form.send-new-link input' ).attr( 'disabled', 'disabled' );
			$( 'p.link-sent' ).show();
		} );
	}
} )( jQuery, mediaWiki );
