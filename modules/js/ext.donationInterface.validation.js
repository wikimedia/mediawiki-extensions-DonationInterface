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
		validate: function () {
			// This funkiness is to make sure we run all the validations and
			// highlight bad values, rather than short-circuiting the second
			// group of tests if "&&" detects that the first tests failed.
			var results = [
					this.validateAmount(),
					this.validatePersonal()
				],
				// Fail if one or more tests failed.
				success = ( results.indexOf( false ) === -1 );

			return success;
		},
		// FIXME: Move global scope functions here
		validateAmount: window.validateAmount,
		validatePersonal: window.validate_personal,
		showErrors: showErrors
	};

	// Set up email error detection and correction
	$( document ).on( 'blur', '#email', function () {
		$( this ).mailcheck( {
			topLevelDomains: [],
			secondLevelDomains: [],
			suggested: function ( element, suggestion ) {
				var message = mw.msg(
					'donate_interface-did-you-mean',
					suggestion.full
				);
				$( '#emailMsg' )
					.removeClass( 'errorMsgHide' )
					.addClass( 'errorMsg' )
					.html( message );
			},
			empty: function ( element ) {
				$( '#emailMsg' )
					.removeClass( 'errorMsg' )
					.addClass( 'errorMsgHide' );
			}
		} );
	} );
	$( document ).on( 'click', '#emailMsg .correction', function () {
		$( '#email' ).val( $( this ).text() );
		$( '#emailMsg' )
			.removeClass( 'errorMsg' )
			.addClass( 'errorMsgHide' );
	} );
} )( jQuery, mediaWiki );
