/* globals Mailcheck */
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
		// Be really conservative - only catch two letter errors
		Mailcheck.domainThreshold = 2; // No way to set from opts!
		$( this ).mailcheck( {
			topLevelDomains: [],
			domains: Mailcheck.defaultDomains.concat( [
				/* Other global domains */
				'email.com', 'games.com' /* AOL */, 'gmx.net', 'hush.com',
				'hushmail.com', 'icloud.com', 'inbox.com', 'lavabit.com',
				'love.com' /* AOL */, 'outlook.com', 'pobox.com',
				'rocketmail.com' /* Yahoo */, 'safe-mail.net',
				'wow.com' /* AOL */, 'ygm.com' /* AOL */,
				'ymail.com' /* Yahoo */, 'zoho.com', 'fastmail.fm',
				'yandex.com',

				/* United States ISP domains */
				'bellsouth.net', 'charter.net', 'comcast.net', 'cox.net',
				'earthlink.net', 'juno.com',

				/* British ISP domains */
				'btinternet.com', 'virginmedia.com', 'blueyonder.co.uk',
				'freeserve.co.uk', 'live.co.uk', 'ntlworld.com', 'o2.co.uk',
				'orange.net', 'sky.com', 'talktalk.co.uk', 'tiscali.co.uk',
				'virgin.net', 'wanadoo.co.uk', 'bt.com',

				/* Domains used in Asia */
				'sina.com', 'qq.com', 'naver.com', 'hanmail.net', 'daum.net',
				'nate.com', 'yahoo.co.jp', 'yahoo.co.kr', 'yahoo.co.id',
				'yahoo.co.in', 'yahoo.com.sg', 'yahoo.com.ph',

				/* French ISP domains */
				'hotmail.fr', 'live.fr', 'laposte.net', 'yahoo.fr',
				'wanadoo.fr', 'orange.fr', 'gmx.fr', 'sfr.fr', 'neuf.fr',
				'free.fr',

				/* German ISP domains */
				'gmx.de', 'hotmail.de', 'live.de', 'online.de',
				't-online.de' /* T-Mobile */, 'web.de', 'yahoo.de',

				/* Russian ISP domains */
				'mail.ru', 'rambler.ru', 'yandex.ru', 'ya.ru', 'list.ru',

				/* Belgian ISP domains */
				'hotmail.be', 'live.be', 'skynet.be', 'voo.be',
				'tvcablenet.be', 'telenet.be',

				/* Argentinian ISP domains */
				'hotmail.com.ar', 'live.com.ar', 'yahoo.com.ar',
				'fibertel.com.ar', 'speedy.com.ar', 'arnet.com.ar',

				/* Domains used in Mexico */
				'hotmail.com', 'gmail.com', 'yahoo.com.mx', 'live.com.mx',
				'yahoo.com', 'hotmail.es', 'live.com', 'hotmail.com.mx',
				'prodigy.net.mx', 'msn.com',

				/* Domains used in Brazil */
				'yahoo.com.br', 'hotmail.com.br', 'outlook.com.br',
				'uol.com.br', 'bol.com.br', 'terra.com.br', 'ig.com.br',
				'itelefonica.com.br', 'r7.com', 'zipmail.com.br', 'globo.com',
				'globomail.com', 'oi.com.br'
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
} )( jQuery, mediaWiki );
