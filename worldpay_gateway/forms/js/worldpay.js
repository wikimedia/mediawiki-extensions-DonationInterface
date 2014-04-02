/**
 * This file is part of the DonationInterface Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:DonationInterface
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
( function ( $, mw ) {

	//hack for janky form style. todo: standardize all forms so this can be removed.
	//$('h3').removeClass('cc_header');
	$('input').removeClass('halfwidth').removeClass('leftmar').addClass('form-control');
	$('#lname').addClass('pull-right');
	$('#zip').addClass('pull-right');
	$('#smCC').addClass('pull-right');
	$('#emailAdd').addClass('pull-right');

	var $accountNumber = $( '#accountNumber' ),
		$accountExpiry = $( '#expiry' ),
		$accountCVC = $( '#cvc' );

	/**
	 * Attempt to validate, on the client side, the form data.
	 *
	 * @returns {boolean} True if all checked form data is valid
	 */
	function validateClientSide() {
		var cardType = $.payment.cardType( $accountNumber.val() ),
			expiry = $accountExpiry.payment( 'cardExpiryVal' );

		$accountNumber.toggleClass( 'invalid', !$.payment.validateCardNumber( $accountNumber.val() ) );
		$accountExpiry.toggleClass( 'invalid', !$.payment.validateCardExpiry( expiry ) );
		$accountCVC.toggleClass( 'invalid', !$.payment.validateCardCVC( $accountCVC.val(), cardType ) );

		return ( $( 'input.invalid' ).length === 0 );
	}

	/**
	 * Submit some form fields up to DonationInterface for remote
	 * verification and storage into the session.
	 *
	 * If validation is successful, the successCallback will be
	 * invoked.
	 */
	function validateServerSide( successCallback ) {
		var api = new mw.Api(),
			fields = [
				// All forms
				'fname', 'lname', 'emailAdd',
				'email-opt',
				'utm_source','utm_medium','utm_campaign','referrer',
				'gateway','payment_method','language','token',
				'order_id','contribution_tracking_id',

				// AVS Countries
				'street','city','state','zip','country',

				// Scary things
				'cvc'
			];

		postdata = {
			action: 'di_wp_validate',
			format: 'json'
		};
		$.each( fields, function( idx, val ) {
			postdata[val] = $( '#' + val ).val();
		});

		$.ajax({
			'url': mw.util.wikiScript( 'api' ),
			'data': postdata,
			'dataType': 'json',
			'type': 'POST',
			'success': function( data ) {
				// XXX: Currently assuming that verification succeeded
				successCallback();
			},
			error: function( xhr ) {
				alert( mw.msg( 'donate_interface-error-msg-general' ) );
			}
		});
	}

	/**
	 * Submit a tokenization request to worldpay. We don't send the
	 * CVV to them in this request because we wont be able to see
	 * the validation result. We also don't send the full address
	 * because we will submit that at authorization time.
	 */
	function submitFormForTokenization() {
		var expiry = $accountExpiry.payment( 'cardExpiryVal' ),
			$form = $( 'form[name="payment"]' );

		function addHFtoF( name, value ) {
			$form.append( $( '<input />', {
				'type': 'hidden',
				'name': name,
				'value': value
			}));
		}

		// Add the required elements to the form
		addHFtoF( 'Action', 'Add' );
		addHFtoF( 'AcctName', [ $( '#fname' ).val(), $( '#lname' ).val()].join( ' ' ).trim() );
		addHFtoF( 'AcctNumber', $accountNumber.val().replace(/\s+/g, '') );
		addHFtoF( 'ExpMonth', expiry.month );
		addHFtoF( 'ExpYear', expiry.year );

		// Add some optional elements that are just nice to have
		addHFtoF( 'FirstName', $( '#fname' ).val() );
		addHFtoF( 'LastName', $( '#lname' ).val() );
		addHFtoF( 'Email', $( '#emailAdd' ).val() );

		$form.submit();
	}

	/**
	 * Initialization
	 */
	$( document ).ready( function () {
		// Initialize jQuery.stripe
		$accountNumber.payment( 'formatCardNumber' );
		$accountExpiry.payment( 'formatCardExpiry' );
		$accountCVC.payment( 'formatCardCVC' );

		$( '#paymentSubmitBtn' ).click(function() {
			if ( validateClientSide() ) {
				validateServerSide( submitFormForTokenization );
			}
			return false;
		});
	});
})( jQuery, mediaWiki );
