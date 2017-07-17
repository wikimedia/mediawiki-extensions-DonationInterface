/* globals Mailcheck */
/**
 * Client-side validation logic for DonationInterface
 * For starters, we just redirect to the existing global functions.
 * They should be rewritten here when we modernize the remaining forms.
 */
( function ( $, mw ) {
	var di = mw.donationInterface = {},
		checkMail = true;

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
			$( '#errorReference' )
				.removeClass( 'errorMsgHide' )
				.addClass( 'errorMsg' );
		}
	}

	/**
	 * Are any errors currently showing, from either server-side or
	 * client-side validation?
	 *
	 * @return {boolean}
	 */
	function hasErrors() {
		return $( '.errorMsg' ).length > 0;
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
		showErrors: showErrors,
		hasErrors: hasErrors
	};

	// Set up email error detection and correction
	$( document ).on( 'blur', '#email', function () {
		if ( !checkMail ) {
			return;
		}
		// Be really conservative - only catch two letter errors
		Mailcheck.domainThreshold = 2; // No way to set from opts!
		$( this ).mailcheck( {
			topLevelDomains: [],
			domains: Mailcheck.defaultDomains.concat( [
				'aim.com', 'alice.it', 'aon.at', 'bellsouth.net', 'bigpond.com',
				'bigpond.net.au', 'blueyonder.co.uk', 'btinternet.com',
				'btopenworld.com', 'charter.net', 'cox.net', 'docomo.ne.jp',
				'earthlink.net', 'email.it', 'embarqmail.com', 'ezweb.ne.jp',
				'fastwebnet.it', 'free.fr', 'frontier.com', 'gmx.at', 'gmx.de',
				'gmx.net', 'hetnet.nl', 'home.nl', 'hotmail.co.jp', 'hotmail.es',
				'hotmail.fr', 'hotmail.it', 'i.softbank.jp', 'iinet.net.au',
				'inwind.it', 'juno.com', 'laposte.net', 'libero.it', 'live.ca',
				'live.co.uk', 'live.com.au', 'live.fr', 'live.it', 'live.nl',
				'mindspring.com', 'netscape.net', 'neuf.fr', 'nifty.com',
				'ntlworld.com', 'o2.pl', 'online.no', 'optonline.net',
				'optusnet.com.au', 'orange.fr', 'pacbell.net', 'planet.nl',
				'q.com', 'qq.com','roadrunner.com', 'rocketmail.com',
				'rogers.com', 'seznam.cz', 'sfr.fr', 'shaw.ca', 'sky.com',
				'skynet.be', 'sympatico.ca', 'talktalk.net', 'telefonica.net',
				'telenet.be', 'telia.com', 'telus.net', 'tin.it',
				'tiscali.co.uk', 'tiscali.it', 'tpg.com.au', 'umich.edu',
				'uol.com.br', 'videotron.ca', 'virgilio.it', 'wanadoo.fr',
				'web.de', 'windstream.net', 'wp.pl', 'xs4all.nl', 'xtra.co.nz',
				'yahoo.ca', 'yahoo.co.in', 'yahoo.co.jp', 'yahoo.com.ar',
				'yahoo.com.au', 'yahoo.com.br', 'yahoo.com.mx', 'yahoo.de',
				'yahoo.es', 'yahoo.fr', 'yahoo.it', 'ybb.ne.jp', 'ymail.com',
				'ziggo.nl'
			] ),
			suggested: function ( element, suggestion ) {
				var message = mw.msg(
					'donate_interface-did-you-mean',
					suggestion.full
				);
				$( '#emailSuggestion' ).show();
				$( '#emailSuggestion span' ).html( message );
			},
			empty: function ( element ) {
				$( '#emailSuggestion' ).hide();
			}
		} );
	} );
	$( document ).on( 'click', '#emailSuggestion .correction', function () {
		$( '#email' ).val( $( this ).text() );
		$( '#emailSuggestion' ).hide();
	} );
	$( document ).on( 'click', '#emailSuggestion .close-button', function () {
		checkMail = false; // Don't bother them again
		$( '#emailSuggestion' ).hide();
	} );
} )( jQuery, mediaWiki );
