( function ( $, mw ) {
	$(function (){
		$( '.submethods' ).after(
			$( '<p id="redirect-explanation">' + mw.msg( 'donate_interface-redirect-explanation' ) + '</p>' )
		);
	});
} )( jQuery, mediaWiki );
