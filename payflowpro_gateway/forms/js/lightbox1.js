$( document ).ready( function () {

	// check for RapidHtml errors and display, if any
	var amountErrorString = "";
	var billingErrorString = "";
	var paymentErrorString = "";

	// generate formatted errors to display
	var temp = [];
	for ( var e in amountErrors )
		if ( amountErrors[e] != "" )
			temp[temp.length] = amountErrors[e];
	amountErrorString = temp.join( "<br />" );

	temp = [];
	for ( var f in billingErrors )
		if ( billingErrors[f] != "" )
			temp[temp.length] = billingErrors[f];
	billingErrorString = temp.join( "<br />" );

	temp = [];
	for ( var g in paymentErrors )
		if ( paymentErrors[g] != "" )
			temp[temp.length] = paymentErrors[g];
	paymentErrorString = temp.join( "<br />" );

	// show the errors
	var prevError = false;
	if ( amountErrorString != "" ) {
		$( "#amtErrorMessages" ).html( amountErrorString );
		prevError = true;
	}
	if ( billingErrorString != "" ) {
		$( "#billingErrorMessages" ).html( billingErrorString );
		if ( !prevError ) {
			prevError = true;
		}
	}
	if ( paymentErrorString != "" ) {
		$( "#paymentErrorMessages" ).html( paymentErrorString );
	}
	
	$( '#dialog' ).dialog( {
		width: 600,
		resizable: false,
        autoOpen: false,
        modal: true,
        title: mw.msg( 'donate_interface-cc-button' )
	} );
	
	// If the form is being reloaded, restore the amount
	var previousAmount = $( 'input[name="amount"]' ).val();
	if ( previousAmount && previousAmount > 0  ) {
		var matched = false;
		$( 'input[name="amountRadio"]' ).each( function( index ) {
			if ( $( this ).val() == previousAmount ) {
				$( this ).attr( 'checked', true );
				matched = true;
			}
		} );
		if ( !matched ) {
			$( 'input#input_amount_other' ).attr( 'checked', true );
			$( 'input#other-amount' ).val( previousAmount );
		}
	}
	
	$( '#cc' ).click( function() {
		if ( validateAmount() ) {
			$( '#dialog' ).dialog( 'open' );
		}
	});
	$( '#pp' ).click( function() {
		if ( validateAmount() ) {
			//$( 'input#pp' ).attr( 'disabled', 'disabled' );
			$( "input[name='gateway']" ).val( 'paypal' );
			document.paypalcontribution.action = "https://wikimediafoundation.org/wiki/Special:ContributionTracking/en";
			document.paypalcontribution.submit();
		}
	});
	
	/* Set selected amount to amount */
	$( "input[name='amountRadio']" ).click( function() { setAmount( $( this ) ); } );
	$( "#other-amount" ).change( function() { setAmount( $( this ) ); } );
	function setAmount(e) { $("input[name='amount']").val( e.val() ); }

	/* number of fieldsets */
	var fieldsetCount = $( '#donationForm' ).children().length;

	/* current position of fieldset / navigation link */
	var current 	= 1;

	/*
	Sum and save the widths of each one of the fieldsets.
	Set the final sum as the total width of the steps element.
	*/
	var stepsWidth	= 0;
    var widths 		= new Array();
	$( '#steps .step' ).each( function(i) {
        var $step 		= $( this );
		widths[i]  		= stepsWidth;
        stepsWidth	 	+= 600; // Hard-coding as $.width() is not working for some reason
    } );
	$( '#steps' ).width( stepsWidth );

	/* To avoid problems in IE, focus the first input of the form */
	$( '#donationForm' ).children( ':first' ).find( ':input:first' ).focus();
	
	/* make continue buttons */
	$( 'a.continue-button' ).button();
	$( 'a.continue-button' ).click( function(e) {
		var $this	= $( this );
		current = $( this ).parent().parent().index() + 1;
		if ( current == fieldsetCount ) {
			finalSubmit();
			return false;
		}
		if ( validateStep( current ) === 1 ) {
			current = current + 1;
			$( '#steps' ).stop().animate( { marginLeft: '-' + widths[current - 1] + 'px' }, 500, 
				function() {
        			$( '#donationForm' ).children( ':nth-child(' + parseInt(current) + ')' ).find( ':input:first' ).focus();
        		}
        	);
		} else {
			$this.blur();
		}
		
        e.preventDefault();
    });
    
    /* Make back button */
	$( 'a.back-button' ).button();
	$( 'a.back-button' ).click( function(e) {
		var $this	= $( this );
		/* Set current to 1 less than previous step */
		current = $( this ).parent().parent().index();
		
		$( '#steps' ).stop().animate( { marginLeft: '+' + widths[current - 1] + 'px' }, 500 );
		
        e.preventDefault();
    });

	/* Hitting tab on the last input of each fieldset makes the form slide to the next step. */
	$( '#donationForm > fieldset' ).each( function() {
		var $fieldset = $( this );
		$fieldset.find( ':input:last' ).keydown( function(e) {
			if ( e.which == 9 ){ // 9 is the char code for tab
				$fieldset.find( 'a.continue-button' ).click();
				/* Force the blur for validation */
				e.preventDefault();
			}
		});
	});

	/*
	Validates errors on all the fieldsets.
	Records if the form has errors in $( '#donationForm' ).data().
	*/
	function validateSteps() {
		var formErrors = false;
		for( var i = 1; i < fieldsetCount; ++i ) {
			var error = validateStep(i);
			if ( error == -1 ) formErrors = true;
		}
		$( '#donationForm' ).data( 'errors', formErrors );
	}
	
	function finalSubmit() {
	
		// Reset the error display
		$( '#card-errors' ).empty();
		
		var formErrors = false;
		for( var i = 1; i <= fieldsetCount; ++i ) {
			var error = validateStep(i);
			if ( error == -1 ) formErrors = true;
		}
		$( '#donationForm' ).data( 'errors', formErrors );
		
		if ( $( '#donationForm' ).data( 'errors' ) ) {
			alert( 'Please correct the errors in the form.' );
			return false;
		} else {
			/* Set country to US */
			$( "input[name='country']" ).val('US' );
							
			/* Set expiration date */
			$( "input[name='expiration']" ).val(
				$( "select[name='mos']" ).val() + $( "select[name='year']" ).val().substring( 2, 4 )
			)
			
			/* Submit the form */
			var sendData = {
				'action': 'donate',
				'gateway': 'payflowpro',
				'currency_code': 'USD',
				'amount': $( "input[name='amount']" ).val(),
				'fname': $( "input[name='fname']" ).val(),
				'lname': $( "input[name='lname']" ).val(),
				'street': $( "input[name='street']" ).val(),
				'city': $( "input[name='city']" ).val(),
				'state': $( 'select#state option:selected' ).val(),
				'zip': $( "input[name='zip']" ).val(),
				'emailAdd': $( "input[name='emailAdd']" ).val(),
				'country': $( "input[name='country']" ).val(),
				'payment_method': 'cc',
				'language': 'en',
				
				'expiration': $( "input[name='expiration']" ).val(),
				'card_num': $( "input[name='card_num']" ).val(),
				'cvv': $( "input[name='cvv']" ).val(),
				'card_type': '',
				
				'format': 'json'
			};
	
			$.ajax( {
				'url': mw.util.wikiScript( 'api' ),
				'data': sendData,
				'dataType': 'json',
				'type': 'POST',
				'success': function( data ) {
					console.debug( data );
					if ( data.result.errors ) {
						var errors = new Array();
						$.each( data.result.errors, function( index, value ) {
							$( '#card-errors' ).append( '<div class="error-msg">'+value+'</div>' );
						} );
					} else {
						if ( data.result.returnurl ) {
							window.location = data.result.returnurl;
						}
					}
				}
			} );
			//document.donationForm.action = $( "input[name='action']" ).val();
			//document.donationForm.submit();
		}
	}

	/*
	validates one fieldset
	returns -1 if errors found, or 1 if not
	*/
	function validateStep( step ) {
		var error = 1;
		$( '#donationForm' ).children( ':nth-child(' + parseInt(step) + ')' ).find( ':input:not(button).required' ).each( function() {
			var $this 		= $( this );
			var valueLength = jQuery.trim( $this.val() ).length;

			if ( valueLength == '' ) {
				error = -1;
				$this.css( 'background-color', '#FFEDEF' );
			} else {
				$this.css( 'background-color', '#FFFFFF' );
			}
		});

		return error;
	}

	/**
	 * Validate the donation amount to make sure it is formatted correctly and at least a minimum amount.
	 */
	function validateAmount() {
		var error = true;
		var amount = $( "input[name='amount']" ).val(); // get the amount
		// Normalize weird amount formats.
		// Don't mess with these unless you know what you're doing.
		amount = amount.replace( /[,.](\d)$/, '\:$10' );
		amount = amount.replace( /[,.](\d)(\d)$/, '\:$1$2' );
		amount = amount.replace( /[,.]/g, '' );
		amount = amount.replace( /:/, '.' );
		$( 'input[name="amount"]' ).val( amount ); // set the new amount back into the form
		
		// Check amount is a real number, sets error as true (good) if no issues
		error = ( amount == null || isNaN( amount ) || amount.value <= 0 );
		
		// Find out the currency code
		if ( $( 'input[name="currency_code"]' ).val() == '' ) {
			var currency_code = 'USD'; // hard-code since we're only running this in the US
			$( "input[name='currency_code']" ).val( currency_code );
		} else {
			var currency_code = $( 'input[name="currency_code"]' ).val();
		}
		
		// Check amount is at least the minimum
		if ( typeof( wgCurrencyMinimums[currency_code] ) == 'undefined' ) {
			wgCurrencyMinimums[currency_code] = 1;
		}
		if ( amount < wgCurrencyMinimums[currency_code] || error ) {
			alert( mw.msg( 'donate_interface-smallamount-error' ).replace( '$1', wgCurrencyMinimums[currency_code] + ' ' + currency_code ) );
			error = true;
			$( '#other-amount' ).val( '' );
			$( '#other-amount' ).focus();
		}
		return !error;
	};
});
