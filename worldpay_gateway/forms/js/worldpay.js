/*global validate_personal:true*/
/**
 * This file is part of the DonationInterface Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:DonationInterface
 *
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
 */
( function ( $, mw ) {

	var $accountNumber = $( '#accountNumber' ),
		$accountExpiry = $( '#expiry' ),
		$accountCVC = $( '#cvc' );

	/**
	 * Attempt to validate, on the client side, the form data.
	 *
	 * @return {boolean} True if all checked form data is valid
	 */
	function validateClientSide() {
		var cardType = $.payment.cardType( $accountNumber.val() ),
			expiry = $accountExpiry.payment( 'cardExpiryVal' ),
			personalResult = validate_personal( document.getElementById( 'worldpayform' ) );

		$accountNumber.toggleClass( 'invalid', !$.payment.validateCardNumber( $accountNumber.val() ) );
		$accountExpiry.toggleClass( 'invalid', !$.payment.validateCardExpiry( expiry ) );
		$accountCVC.toggleClass( 'invalid', !$.payment.validateCardCVC( $accountCVC.val(), cardType ) );

		validate_cc();

		return ( personalResult && $( 'input.invalid' ).length === 0 );
	}

	/**
	 * Validate individual credit card fields are getting filled in.
	 *
	 * Show and hide errors as appropriate.
	 */
	function validate_cc() {
		var types = [
				{ '.ccNumberError': $accountNumber },
				{ '.ccExpiryError': $accountExpiry },
				{ '.ccCVCError': $accountCVC }
			];

		types.forEach( function ( type ) {
			var key;
			for ( key in type ) {
				if ( $( type[ key ] ).hasClass( 'invalid' ) ) {
					$( key ).addClass( 'show' );
				} else if ( !$( type[ key ] ).hasClass( 'invalid' ) ) {
					$( key ).removeClass( 'show' ).addClass( 'hide' );
				}
			}
		} );

	}

	/**
	 * Submit some form fields up to DonationInterface for remote
	 * verification and storage into the session.
	 *
	 * If validation is successful, the successCallback will be
	 * invoked.
	 */
	function validateServerSide( successCallback ) {
		var fields = [
				// All forms
				'fname', 'lname', 'emailAdd',
				'email-opt',
				'utm_source', 'utm_medium', 'utm_campaign', 'referrer',
				'gateway', 'payment_method', 'language', 'token',
				'order_id', 'contribution_tracking_id',

				// AVS Countries
				'street', 'city', 'state', 'zip', 'country',

				// Scary things
				'cvc'
			],
			postdata = {
				action: 'di_wp_validate',
				format: 'json'
			};

		$.each( fields, function ( idx, val ) {
			postdata[ val ] = $( '#' + val ).val();
		} );

		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST',
			success: function ( data ) {
				if ( data.errors ) {
					// TODO: This sucks; improve it
					// Form fields have errors; each subkey in this array
					// corresponds to a form field with an error
					var errors = [];
					$.each( data.errors, function ( idx, str ) {
						errors.push( str );
					} );
					window.alert( errors.join( '\n' ) );
					$( '#paymentSubmitBtn' ).removeClass( 'disabled' );
				} else if ( data.ottResult ) {
					successCallback( data.ottResult );
				} else {
					window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
					$( '#paymentSubmitBtn' ).removeClass( 'disabled' );
				}
			},
			error: function ( xhr ) {
				window.alert( mw.msg( 'donate_interface-error-msg-general' ) );
				$( '#paymentSubmitBtn' ).removeClass( 'disabled' );
			}
		} );
	}

	/**
	 * Submit a tokenization request to worldpay. We don't send the
	 * CVV to them in this request because we wont be able to see
	 * the validation result. We also don't send the full address
	 * because we will submit that at authorization time.
	 */
	function submitFormForTokenization( ottResult ) {
		/*jshint camelcase: false */

		var expiry = $accountExpiry.payment( 'cardExpiryVal' ),
			$form = $( 'form[name="payment"]' );

		$form.prop( 'action', ottResult.wp_process_url );
		$( '#wp_one_time_token' ).val( ottResult.wp_one_time_token );

		function addHFtoF( name, value ) {
			$form.append( $( '<input />', {
				type: 'hidden',
				name: name,
				value: value
			} ) );
		}

		// Add the required elements to the form
		addHFtoF( 'Action', 'Add' );
		addHFtoF( 'AcctName', [ $( '#fname' ).val(), $( '#lname' ).val() ].join( ' ' ).trim() );
		addHFtoF( 'AcctNumber', $accountNumber.val().replace( /\s+/g, '' ) );
		addHFtoF( 'ExpMonth', ( '0' + expiry.month ).slice( -2 ) );
		addHFtoF( 'ExpYear', ( '000' + expiry.year ).slice( -4 ) );

		// Add some optional elements that are just nice to have
		addHFtoF( 'FirstName', $( '#fname' ).val() );
		addHFtoF( 'LastName', $( '#lname' ).val() );
		addHFtoF( 'Email', $( '#emailAdd' ).val() );

		$form.submit();
	}

	function submitForm() {
		var button = $( '#paymentSubmitBtn' );
		if ( button.hasClass( 'disabled' ) ) {
			return false;
		}

		button.addClass( 'disabled' );

		if ( validateClientSide() ) {
			validateServerSide( submitFormForTokenization );
		} else {
			button.removeClass( 'disabled' );
		}
		return false;
	}

	/**
	 * Initialization
	 */
	$( document ).ready( function () {
		// Initialize jQuery.stripe
		$accountNumber.payment( 'formatCardNumber' );
		$accountExpiry.payment( 'formatCardExpiry' );
		$accountCVC.payment( 'formatCardCVC' );

		$( '.form-control' ).keypress( function ( e ) {
			if ( e.which === 13 ) {
				submitForm();
			}
		} );

		$( '#paymentSubmitBtn' ).click( function () {
			submitForm();
		} );

		$( '#ddTestOptions' ).click( function () {
			$( '#ddTestCCArea' ).removeClass( 'hide' ).addClass( 'show' );
		} );
		$( '#cvv-info' ).hover( function () {
			$( '#cvv-codes' ).show();
		}, function () {
			$( '#cvv-codes' ).hide();
		} );
		$( '#cvv-info' ).click( function () {
			$( '#cvv-codes' ).addClass( 'popped' ).show();
			$( 'body' ).addClass( 'pop-shown' );
			return false;
		} );
		$( document ).on( 'click', 'body.pop-shown', function () {
			$( '.popped' ).removeClass( 'popped' ).hide();
			$( 'body' ).removeClass( 'pop-shown' );
		} );
	} );
}( jQuery, mediaWiki ) );
