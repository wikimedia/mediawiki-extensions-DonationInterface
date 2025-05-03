( function ( $ ) {
	$( () => {
		$( '.emailpreferences-form-col-content-salutation-email-edit-link' ).click( ( e ) => {
				e.preventDefault();
			$( '.emailpreferences-form-col-content-salutation-email-edit-input' ).show();
			$( '.emailpreferences-form-col-content-salutation-email' ).hide();
			$( '.emailpreferences-form-col-content-salutation-email-edit' ).hide();
		} );
		const $submitButton = $( '#save' ),
			$emailField = $( '#email' ),
			isEmailValid = function () {
				const email = $emailField.val().trim(),
					dotPos = email.lastIndexOf( '.' ),
					atPos = email.indexOf( '@' ),
					lastAtPos = email.lastIndexOf( '@' );

				// Needs to be not empty,
				return email !== '' &&
					// have a '.',
					dotPos > -1 &&
					// have (but not start with) an '@',
					atPos > 0 &&
					// only have ONE '@'
					atPos === lastAtPos &&
					// have at least 1 char between '@' and '.',
					dotPos > atPos + 1 &&
					// and not end with a dot
					dotPos < email.length - 1;
			};

		if ( $emailField.length === 1 ) {
			$submitButton.click( () => {
				if ( !isEmailValid() ) {
					$emailField.addClass( 'errorHighlight' );
					return false;
				}
				return true;
			} );

			$emailField.change( () => {
				if ( isEmailValid() ) {
					$emailField.removeClass( 'errorHighlight' );
				}
			} );
		}

	} );
} )( jQuery );
