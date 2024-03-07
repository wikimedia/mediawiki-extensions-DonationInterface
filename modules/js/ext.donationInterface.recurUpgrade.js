( function ( $ ) {
	$( function () {
		var $submitButton = $( '#save' ),
			$amountField = $( 'input[name=upgrade_amount]' ),
			$otherAmountField = $( 'input[name=upgrade_amount_other]' ),
			$totalMessage = $( '.recurUpgradeMessageToggle' ),
			$newTotalAmount = $( '.recurUpgradeMessageToggle strong' ),
			$form = $( 'form' ),
			originalAmount = $form.attr( 'data-original-amount' ),
			currency = $form.attr( 'data-currency' ),
			formatter;

		// Avoid console errors in case currency is not supplied
		if ( currency && currency.length === 3 ) {
			formatter = new Intl.NumberFormat(
				$form.attr( 'data-locale' ), {
					style: 'currency',
					currency: $form.attr( 'data-currency' )
				}
			);
		}

		function setTotalAndSubmitState() {
			var valueIsValid = false,
				value = $amountField.filter( ':checked' ).val();

			if ( value === 'other' ) {
				value = $otherAmountField.val();
				valueIsValid = ( value > 0 );
			} else if ( value > 0 ) {
				valueIsValid = true;
				$otherAmountField.val( '' );
			}

			if ( valueIsValid ) {
				$submitButton.removeClass( 'disabled' );
				$submitButton.prop( 'disabled', false );
				$newTotalAmount.text(
					// Use unary + operator to tell JS to treat both as numbers, not strings
					formatter.format( ( +originalAmount ) + ( +value ) )
				);
				$totalMessage.show();
			} else {
				$submitButton.addClass( 'disabled' );
				$submitButton.prop( 'disabled', true );
				$totalMessage.hide();
			}
		}

		$amountField.change( setTotalAndSubmitState );
		// We need to use setInterval because the new value of the other amount
		// field is not immediately available in the keyup handler
		$otherAmountField.on( 'change keyup', function () {
			setInterval( setTotalAndSubmitState, 0 );
		} );
		$otherAmountField.focus( function () {
			$amountField.filter( '[value=other]' ).prop( 'checked', true );
			setTotalAndSubmitState();
		} );
		setTotalAndSubmitState();
	} );
} )( jQuery );
