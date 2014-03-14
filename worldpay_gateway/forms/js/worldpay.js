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
$( document ).ready( function () {
	var $accountNumber = $( '#accountNumber' ),
		$accountExpiry = $( '#expiry' ),
		$accountCVC = $( '#cvc' );

	/**
	 * Attempt to validate form data before tokenization and DI submission
	 *
	 * @returns {boolean} True if all data is valid
	 */
	function validateData() {
		var cardType = $.payment.cardType( $accountNumber.val() ),
			expiry = $accountExpiry.payment( 'cardExpiryVal' );

		$accountNumber.toggleClass( 'invalid', !$.payment.validateCardNumber( $accountNumber.val() ) );
		$accountExpiry.toggleClass( 'invalid', !$.payment.validateCardExpiry( expiry ) );
		$accountCVC.toggleClass( 'invalid', !$.payment.validateCardCVC( $accountCVC.val(), cardType ) );

		return ( $( 'input.invalid' ).length === 0 );
	}

	/**
	 * Submit a tokenization request to worldpay
	 */
	function tokenizeCcData() {
		var expiry = $accountExpiry.payment( 'cardExpiryVal' ),
			$form = $( 'form[name="payment"]' );

		// Add the required elements to the form
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'Action',
			'value': 'Add'
		}));
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'AcctName',
			'value': [ $( '#fname' ).val(), $( '#lname' ).val()].join( ' ' ).trim(),
		}));
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'AcctNumber',
			'value': $accountNumber.val(),
		}));
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'ExpMonth',
			'value': expiry.month
		}));
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'ExpYear',
			'value': expiry.year
		}));
		$form.append( $( '<input />', {
			'type': 'hidden',
			'name': 'CVN',
			'value': $accountCVC.val()
		}));

		$form.submit();
	}

	// Initialize jQuery.stripe
	$accountNumber.payment( 'formatCardNumber' );
	$accountExpiry.payment( 'formatCardExpiry' );
	$accountCVC.payment( 'formatCardCVC' );

	$( '#paymentSubmitBtn' ).click(function() {
		if ( validateData() ) {
			tokenizeCcData();
		}
	});
});