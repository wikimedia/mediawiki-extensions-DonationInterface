( function ( $ ) {
	$( function () {
		var $submitButton = $( '#save' ),
			$amountField = $( 'input[name=upgrade_amount]' ),
			$otherAmountField = $( 'input[name=upgrade_amount_other]' ),
			$totalMessage = $( '.recurUpgradeMessageToggle' ),
			$newTotalAmount = $( '.recurUpgradeMessageToggle strong' ),
			$form = $( 'form' ),
			originalAmount = +( $form.attr( 'data-original-amount' ) ),
			currency = $form.attr( 'data-currency' ),
			maximum = +( $form.attr( 'data-maximum' ) ),
			nextDateFormatted = $form.attr( 'data-next-date-formatted' ),
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
			var valueIsValid,
				value = $amountField.filter( ':checked' ).val(),
				formattedValue;

			if ( value === 'other' ) {
				value = $otherAmountField.val();
			} else {
				$otherAmountField.val( '' );
			}
			// Use unary + operator to tell JS to treat it as a number, not a string
			value = +value;
			valueIsValid = ( value > 0 && value <= maximum );

			if ( valueIsValid ) {
				formattedValue = formatter.format( originalAmount + value );
				$submitButton.removeClass( 'disabled' );
				$submitButton.prop( 'disabled', false );
				if ( $newTotalAmount.length > 0 ) {
					$newTotalAmount.text( formattedValue );
				} else {
					$totalMessage.text( mw.message( 'recurupgrade-upgrading-new-amount-and-date',
						formattedValue,
						nextDateFormatted
					) );
				}
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
