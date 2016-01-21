/*global self:false */
if ( top.frames.length !== 0 ) {
	jQuery( document ).ready( function () {
		// we're going to immediately redirect.
		// Hide the page contents (skin, mostly) so it doesn't appear in the iframe while we're waiting for the reload.
		jQuery( 'body' ).children().attr( 'style', 'display:none' );
	} );
	top.location = self.document.location + '&liberated=1';
}
