/**
 * Client-side validation logic for DonationInterface
 * For starters, we just redirect to the existing global functions.
 * They should be rewritten here when we modernize the remaining forms.
 */
( function ( $, mw ) {
	var di = mw.donationInterface = {};

	function showErrors( errors ) {
		var generalErrors = [];

		$.each( errors, function ( field, message ) {
			var $messageField = $( '#' + field + 'Msg' );

			if ( $messageField.length > 0 ) {
				$( '#' + field ).addClass( 'errorHighlight' );
				$messageField
					.removeClass( 'errorMsgHide' )
					.addClass( 'errorMsg' )
					.text( message );
			} else {
				generalErrors.push( message );
			}
		} );
		if ( generalErrors.length > 0 ) {
			$( '#topError' ).html(
				generalErrors.join( '<br/>' )
			);
		}
	}

	di.validation = {
		validateAmount: window.validateAmount,
		validatePersonal: window.validate_personal,
		showErrors: showErrors
	};
} )( jQuery, mediaWiki );
