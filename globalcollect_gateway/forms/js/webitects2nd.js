/*global validateAmount:true, setAmount:true, showAmount:true, actionURL:true, validate_personal: true, displayCreditCardForm:true*/
/*exported setAmount*/
$( document ).ready( function () {

	var amount,
		matched,
		otherAmount,
		previousAmount;

	$( '#step2header' ).show();
	$( '#step2wrapper' ).show();

	window.displayErrors();

	$( '#paymentContinueBtn' ).on( 'click', function () {
		if ( validate_personal( document.paypalcontribution ) && validateAmount() ) {
			displayCreditCardForm();
		}
	} );

	$( '#bt-continueBtn' ).on( 'click', function () {
		if ( $( '#bt-continueBtn' ).hasClass( 'disabled' ) ) {
			return false;
		}
		if ( validate_personal( document.paypalcontribution ) && validateAmount() ) {
			$( '#bt-continueBtn' ).addClass( 'disabled' );
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );

	// Set the cards to progress to step 3
	$( '.cardradio' ).on( 'click', function () {
		if ( validate_personal( document.paypalcontribution ) && validateAmount() ) {
			displayCreditCardForm();
		} else {
			// show the continue button to indicate how to get to step 3 since they
			// have already clicked on a card image
			$( '#paymentContinue' ).show();
		}
	} );

	// init all of the header actions
	$( '#step2header' ).click( function () {
		if ( validateAmount() ) {
			window.showStep2();
		}
	} );
	$( '#step3header' ).click( function () {
		if ( validateAmount() ) {
			displayCreditCardForm();
		}
	} );

	// check to see if amount was passed from the previous step
	amount = $( 'input[name="amount"]' ); // get the amount field
	if ( amount === null || isNaN( amount.val() ) || amount.val() <= 0 ) {
		// the amount is not set
		$( '#step1wrapper' ).slideDown();
		$( '#selected-amount' ).text( '(' + $( 'input[name="currency_code"]' ).val() + ')' );
	} else {
		showAmount( $( 'input[name="amount"]' ) );
	}

	// For when people switch back to Other from another value
	$( '#input_amount_other' ).click( function () {
		otherAmount = $( 'input#other-amount' ).val();
		if ( otherAmount ) {
			setAmount( $( 'input#other-amount' ) );
		}
	} );
	// Set selected amount to amount
	$( 'input[name="amountRadio"]' ).click( function () {
		if ( !isNaN( $( this ).val() ) ) {
			setAmount( $( this ) );
		}
	} );
	// reset the amount field when "other" is changed
	$( '#other-amount' ).keyup( function () {
		if ( $( '#input_amount_other' ).is( ':checked' ) ) {
			setAmount( $( this ) );
		}
	} );
	// change the amount when "other" is focused
	$( '#other-amount' ).focus( function () {
		$( '#input_amount_other' ).attr( 'checked', true );
		otherAmount = $( 'input#other-amount' ).val();
		if ( otherAmount ) {
			setAmount( $( 'input#other-amount' ) );
		}
	} );

	$( '#step1header' ).click( function () {
		// show the correct sections
		$( '#step1wrapper' ).slideDown();
		$( '#step2wrapper' ).slideDown();
		$( '#step3wrapper' ).slideUp();
		$( '#change-amount' ).hide();
	} );

	// If the form is being reloaded, restore the amount
	previousAmount = $( 'input[name="amount"]' ).val();
	if ( previousAmount && previousAmount > 0  ) {
		matched = false;
		$( 'input[name="amountRadio"]' ).each( function ( index ) {
			if ( $( this ).val() === previousAmount ) {
				$( this ).attr( 'checked', true );
				matched = true;
			}
		} );
		if ( !matched ) {
			$( 'input#input_amount_other' ).attr( 'checked', true );
			$( 'input#other-amount' ).val( previousAmount );
		}
	}
} );

// set the hidden amount input to the value of the selected element
function setAmount( e ) {
	$( 'input[name="amount"]' ).val( e.val() );
}
// Display selected amount
window.showAmount = function ( e ) {
	var currency_code = '';
	if ( $( 'input[name="currency_code"]' ).length ) {
		currency_code = $( 'input[name="currency_code"]' ).val();
	}
	if ( $( 'select[name="currency_code"]' ).length ) {
		currency_code = $( 'select[name="currency_code"]' ).val();
	}
	$( '#selected-amount' ).text( +e.val() + ' ' + currency_code );
	$( '#change-amount' ).show();
};

window.showStep1 = function () {
	// show the correct sections
	$( '#step1wrapper' ).slideDown();
	$( '#step2wrapper' ).slideUp();
	$( '#step3wrapper' ).slideUp();
	$( '#change-amount' ).hide();
	$( '#change-billing' ).show();
	$( '#change-payment' ).show();
	$( '#step1header' ).show(); // just in case
};

window.showStep2 = function () {
	if ( $( '#step3wrapper' ).is( ':visible' ) ) {
		$( '#paymentContinue' ).show(); // Show continue button in 2nd section
	}
	// show the correct sections
	$( '#step1wrapper' ).slideUp();
	$( '#step2wrapper' ).slideDown();
	$( '#step3wrapper' ).slideUp();
	$( '#change-amount' ).show();
	$( '#change-billing' ).hide();
	$( '#change-payment' ).show();
	$( '#step2header' ).show(); // just in case
};

window.showStep3 = function () {
	// show the correct sections
	$( '#step1wrapper' ).slideUp();
	$( '#step2wrapper' ).slideUp();
	$( '#step3wrapper' ).slideDown();
	$( '#change-amount' ).show();
	$( '#change-billing' ).show();
	$( '#change-payment' ).hide();
	$( '#step3header' ).show(); // just in case
};
