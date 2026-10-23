/**
 * Shared utility for wallet payments (Google Pay, Apple Pay, Venmo, PayPal).
 *
 * Wallets supply the donor's name and email from their stored account data, but
 * that data can be missing, empty, or contain only punctuation (e.g. ".").
 * Without a valid name and email we cannot charge recurring donations.
 *
 * This module provides:
 *   hasValidWalletName( name ) — true if the string contains at least one letter.
 *   showWalletFallbackFields( $container, existingEmail, onSubmit ) — hides the
 *     wallet button container and injects first-name, last-name, and email fields
 *     plus a Continue button so the donor can supply the missing information.
 */

/**
 * Returns true if `name` contains at least one Unicode letter.
 * Rejects empty strings, whitespace-only strings, and punctuation-only
 * values such as ".".
 *
 * @param {*} name
 * @return {boolean}
 */
function hasValidWalletName( name ) {
	if ( typeof name !== 'string' ) {
		return false;
	}
	return Array.from( name ).some( ( char ) => char.toLowerCase() !== char.toUpperCase() );
}

function hasValidWalletEmail( email ) {
	if ( typeof email !== 'string' ) {
		return false;
	}
	// Validates non-space characters surrounding the '@' and a top-level domain '.'
	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	return emailRegex.test( email.trim() );
}

/**
 * Hide `$container` and inject first-name / last-name / email inputs plus a
 * Continue button immediately after it.  Calls `onSubmit( firstName, lastName,
 * email )` once the donor has entered valid data.
 *
 * @param {jQuery} $container  The wallet button element to hide.
 * @param {string|undefined} existingEmail  Pre-fill the email field when the
 *   wallet returned one (even if it turned out to be empty).
 * @param {Function} onSubmit  Called with ( firstName, lastName, email ).
 */
function showWalletFallbackFields( $container, existingEmail, onSubmit ) {
	const $existingModal = $( '#wallet-fallback-modal' );
	if ( $existingModal.length ) {
		$existingModal.remove();
	}

	$( '.adyen-checkout__overlay, .adyen-checkout__sheet, .adyen-checkout__modal, .adyen-checkout__panel, .adyen-checkout__content' )
		.css( {
			display: 'none !important',
			visibility: 'hidden !important',
			opacity: '0 !important',
			zIndex: '-1 !important'
		} );

	const $backdrop = $( '<div id="wallet-fallback-modal-backdrop" style="position:fixed !important;top:0 !important;left:0 !important;right:0 !important;bottom:0 !important;background:rgba(0,0,0,0.55);z-index:2147483647 !important;display:flex !important;align-items:center !important;justify-content:center !important;pointer-events:auto !important;"></div>' );
	const $modal = $( '<div id="wallet-fallback-modal" role="dialog" aria-modal="true" tabindex="-1" style="position:relative !important;z-index:2147483648 !important;background:#fff;border-radius:8px;max-width:420px;width:calc(100% - 2rem);padding:1.25rem 1.25rem 1rem;box-shadow:0 20px 60px rgba(0,0,0,0.45);pointer-events:auto !important;"></div>' );
	const $close = $( '<button type="button" aria-label="Close" style="position:absolute;top:12px;right:12px;border:none;background:transparent;font-size:1.5rem;cursor:pointer;">×</button>' );
	const $dialogWrap = $( '<div style="position:relative;"></div>' );
	const $header = $( '<h3 style="margin:0 0 0.75rem;">Complete your donation details</h3>' );

	const $firstNameRow = $( '<div class="wallet-fallback-row" style="display:flex;flex-direction:column;margin-bottom:0.75rem;"></div>' );
	$firstNameRow.append(
		$( '<label>' ).attr( 'for', 'wallet-first-name' ).text( mw.msg( 'donate_interface-donor-first_name' ) ).css( { marginBottom: '0.25rem' } ),
		$( '<input type="text" />' ).attr( { id: 'wallet-first-name', name: 'first_name' } ).css( { width: '100%', boxSizing: 'border-box' } ),
		$( '<span class="errorMsg" style="color:#a51d1d;min-height:1.1em;"></span>' ).attr( 'id', 'wallet-first-name-error' )
	);

	const $lastNameRow = $( '<div class="wallet-fallback-row" style="display:flex;flex-direction:column;margin-bottom:0.75rem;"></div>' );
	$lastNameRow.append(
		$( '<label>' ).attr( 'for', 'wallet-last-name' ).text( mw.msg( 'donate_interface-donor-last_name' ) ).css( { marginBottom: '0.25rem' } ),
		$( '<input type="text" />' ).attr( { id: 'wallet-last-name', name: 'last_name' } ).css( { width: '100%', boxSizing: 'border-box' } ),
		$( '<span class="errorMsg" style="color:#a51d1d;min-height:1.1em;"></span>' ).attr( 'id', 'wallet-last-name-error' )
	);

	const $emailRow = $( '<div class="wallet-fallback-row" style="display:flex;flex-direction:column;margin-bottom:1rem;"></div>' );
	$emailRow.append(
		$( '<label>' ).attr( 'for', 'wallet-email' ).text( mw.msg( 'donate_interface-donor-email' ) ).css( { marginBottom: '0.25rem' } ),
		$( '<input type="email" />' ).attr( { id: 'wallet-email', name: 'email' } ).val( existingEmail || '' ).css( { width: '100%', boxSizing: 'border-box' } ),
		$( '<span class="errorMsg" style="color:#a51d1d;min-height:1.1em;"></span>' ).attr( 'id', 'wallet-email-error' )
	);

	const $continueBtn = $( '<button type="button" class="btn enabled" id="wallet-continue-btn" style="width:100%;margin-top:0.5rem;"></button>' )
		.text( mw.msg( 'donate_interface-submit-button' ) );
	const $cancelBtn = $( '<button type="button" class="btn" style="width:100%;margin-top:0.5rem;display:none;"></button>' )
		.text( 'Close' );

	$dialogWrap.append( $close, $header, $firstNameRow, $lastNameRow, $emailRow, $continueBtn, $cancelBtn );
	$modal.append( $dialogWrap );
	$backdrop.append( $modal );
	$( 'body' ).append( $backdrop );
	$container.hide();
	$( '#wallet-first-name' ).trigger( 'focus' );

	$close.on( 'click', () => {
		$backdrop.remove();
		$container.show();
	} );

	$continueBtn.on( 'click', ( e ) => {
		e.preventDefault();

		const firstName = $( '#wallet-first-name' ).val().trim();
		const lastName = $( '#wallet-last-name' ).val().trim();
		const email = $( '#wallet-email' ).val().trim();
		let hasError = false;

		$( '#wallet-first-name-error, #wallet-last-name-error, #wallet-email-error' ).text( '' );

		if ( !hasValidWalletName( firstName ) ) {
			$( '#wallet-first-name-error' ).text( mw.msg( 'donate_interface-error-msg-first_name' ) );
			hasError = true;
		}
		if ( !hasValidWalletName( lastName ) ) {
			$( '#wallet-last-name-error' ).text( mw.msg( 'donate_interface-error-msg-last_name' ) );
			hasError = true;
		}
		if ( !hasValidWalletEmail( email ) ) {
			$( '#wallet-email-error' ).text( mw.msg( 'donate_interface-error-msg-email' ) );
			hasError = true;
		}

		if ( hasError ) {
			return;
		}

		$backdrop.remove();
		onSubmit( firstName, lastName, email );
	} );
}

module.exports = { hasValidWalletName, hasValidWalletEmail, showWalletFallbackFields };
