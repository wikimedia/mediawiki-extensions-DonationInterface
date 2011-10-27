/**
 * GlobalCollect Validation
 *
 * Things you need to know:
 * - jquery.validate.js is awesome
 * - Howto change the default messages: @link http://stackoverflow.com/questions/2457032/jquery-validation-change-default-error-message
 *
 * @since r100950
 */

/*******************************************************************************

Helpers

*******************************************************************************/

/**
 * clearField
 *
 * @param object field
 * @param string field
 */
window.clearField = function( field, defaultValue ) {
	if (field.value == defaultValue) {
		field.value = '';
		field.style.color = 'black';
	}
};

/**
 * isset
 *
 * @param mixed varname
 */
function isset( varname ){
    if ( typeof( varname ) == "undefined" ) {
        return false;
    }
    else {
        return true;
    }
}

/**
 * empty
 *
 * @param mixed value
 */
function empty( value ) {

    var key;
 
    if ( value === '' || value === 0 || value === '0' || value === null || value === false || typeof value === 'undefined' ) {
        return true;
    }
    else if ( typeof value == 'object' ) {
        for ( key in value ) {
            return false;
        }
        return true;
    }
 
    return false;
}

/*******************************************************************************

Validate Elements

*******************************************************************************/

/**
 * Validate the element: amount
 *
 */
function validateElementAmount( options ) {
    
	$().ready(function() {
		
		/**
		 * Convert to an integer value because we will not test for:
		 * - 1.00
		 * - 0.00
		 * - 1,00
		 */
		jQuery.validator.addMethod("requirefunds", function(value, element, params) {
			
			var integerValue = parseInt( value );
			
			if ( isset( params.min ) ) {
				params.min = parseInt( params.min );
			}
			else {
				params.min = 0;
			}
			//console.log('value: ' + value);
			//console.log('integerValue: ' + integerValue);
			//console.log(params);
			
			return integerValue >= params.min;
		}, mw.msg( 'donate_interface-error-msg-invalid-amount' ) );

        $("#amount").rules("add", 
            {
                required: true,
				requirefunds: { 
					min: 1,
				},
            }
        );
    });
}

/**
 * Validate the element: emailAdd
 *
 */
function validateElementEmail( options ) {
    
	$().ready(function() {

        $("#emailAdd").rules("add", 
            {
                required: true,
                email: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-emailAdd' ),
                    email: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-emailAdd' ),
                }
            }
        );
    });
}

/**
 * Validate the element: fname
 *
 */
function validateElementFirstName( options ) {
    
	$().ready(function() {

        $("#fname").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-fname' ),
                }
            }
        );
    });
}

/**
 * Validate the element: lname
 *
 */
function validateElementLastName( options ) {
    
	$().ready(function() {

        $("#lname").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-lname' ),
                }
            }
        );
    });
}

/**
 * Validate the element: street
 *
 */
function validateElementStreet( options ) {
    
	$().ready(function() {

        $("#street").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-street' ),
                }
            }
        );
    });
}

/**
 * Validate the element: city
 *
 */
function validateElementCity( options ) {
    
	$().ready(function() {

        $("#city").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-city' ),
                }
            }
        );
    });
}

/**
 * Validate the element: state
 *
 * @todo
 * - This should only be required for the US at this point.
 * - It will be required outside the US, but that may be dependent on payment type.
 *
 */
function validateElementState( options ) {
    
	$().ready(function() {

        $("#state").rules("add", 
            {
                required: true,
                notEqual: 'YY',
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-state' ),
                    notEqual: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-state' ),
                }
            }
        );
    });
}

/**
 * Validate the element: zip
 *
 */
function validateElementZip( options ) {
    
	$().ready(function() {

        $("#zip").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-zip' ),
                }
            }
        );
    });
}

/**
 * Validate the element: country
 *
 * @todo
 * - This should handle dropdowns
 *
 */
function validateElementCountry( options ) {
    
	$().ready(function() {

        $("#country").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ),
                }
            }
        );
    });
}

/**
 * Validate the element: card_num
 *
 * @todo
 * - There are more options we can test. They are commented out.
 *
 */
function validateElementCardNumber( options ) {
    
	$().ready(function() {

        $("#card_num").rules("add", 
            {
                required: true,
                //creditcard: true,
                //creditcardtypes: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-card_num' ),
                }
            }
        );
    });
}

/**
 * Validate the element: cvv
 *
 */
function validateElementCvv( options ) {
    
	$().ready(function() {

        $("#cvv").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-cvv' ),
                }
            }
        );
    });
}

/**
 * Validate the element: payment_method
 *
 */
function validateElementPaymentMethod( options ) {
    
	$().ready(function() {

		// Hidden elements do not have ids
		$('#' + options.formId + " input[name=payment_method]").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-payment_method' ),
                }
            }
        );
    });
}

/**
 * Validate the element: payment_submethod
 *
 */
function validateElementPaymentSubmethod( options ) {
    
	$().ready(function() {

		// Hidden elements do not have ids
		$('#' + options.formId + " input[name=payment_submethod]").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-payment_submethod' ),
                }
            }
        );
    });
}

/**
 * Validate the element: issuer_id
 *
 */
function validateElementIssuerId( options ) {
    
	$().ready(function() {

        $("#issuer_id").rules("add", 
            {
                required: true,
                messages: {
                    required: mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-issuer_id' ),
                }
            }
        );
    });
}

/*******************************************************************************

Validate Element Groups

*******************************************************************************/

/**
 * Validate Bank Transfers
 *
 */

function validateForm( options ) {
/*
	$("#form1").validate({
		errorLabelContainer: $("#form1 div.error")
	});
	
	var container = $('div.container');
	// validate the form when it is submitted
	var validator = $("#form2").validate({
		errorContainer: container,
		errorLabelContainer: $("ol", container),
		wrapper: 'li',
		meta: "validate"
	});
	
	$(".cancel").click(function() {
		validator.resetForm();
	});

*/
	$().ready(function() {
	
		if ( !isset( options.formId ) ) {
			options.formId = '';
		}
	
		if ( empty( options.formId ) ) {
			
			// An id must be specified to validate the form.
			return;
		}
	
		var validateOptions = {
			ignore: ':hidden',
		}
		
		$("#" + options.formId).validate();

		// Check for payment_method
		if ( !isset( options.payment_method ) ) {
			options.payment_method = '';
		}
	
		// Check for payment_submethod
		if ( !isset( options.payment_submethod ) ) {
			options.payment_submethod = '';
		}
	
		//console.log( options );
		// Initialize validate options if not set.
		if ( !isset( options.validate ) ) {
			options.validate = {};
		}
		//console.log( options.validate );
	
		/*
		 * Setup default validations based on payment_method
		 */
		
		if ( options.payment_method == 'cc' ) {
			
			// card_num and cvv are not validated on our site.
		}
		else if ( options.payment_method == 'bt' ) {
			
			options.validate.payment = true;
		}
		else if ( options.payment_method == 'rtbt' ) {
			
			options.validate.payment = true;
		}
		//console.log( options.validate );
	
		/*
		 * Setup default validations based on payment_submethod
		 */
		
		if ( options.payment_submethod == 'rtbt_ideal' ) {
			
			// Ideal requires issuer_id
			options.validate.issuerId = true;
		}
		else if ( options.payment_submethod == 'rtbt_eps' ) {
			
			// eps requires issuer_id
			options.validate.issuerId = true;
		}
		//console.log( options.validate );
	
		/*
		 * Standard elements groups to validate
		 */
		
		// Options: Validate address
		if ( !isset( options.validate.address ) ) {
			options.validate.address = true;
		}
		
		// Options: Validate amount
		if ( !isset( options.validate.amount ) ) {
			options.validate.amount = true;
		}
		
		// Options: Validate creditCard
		if ( !isset( options.validate.creditCard ) ) {
			options.validate.creditCard = false;
		}
		
		// Options: Validate email
		if ( !isset( options.validate.email ) ) {
			options.validate.email = true;
		}
		
		// Options: Validate issuerId
		if ( !isset( options.validate.issuerId ) ) {
			options.validate.issuerId = false;
		}
		
		// Options: Validate name
		if ( !isset( options.validate.name ) ) {
			options.validate.name = true;
		}
		
		// Options: Validate payment
		if ( !isset( options.validate.payment ) ) {
			options.validate.payment = false;
		}
		//console.log( options.validate );
	
		/*
		 * Standard elements groups to validate if enabled
		 */
		
		// Validate: address
		if ( options.validate.address ) {
			validateElementStreet( options );
			validateElementCity( options );
			validateElementState( options );
			//validateElementZip( options );
			validateElementCountry( options );
		}
		
		// Validate: amount
		if ( options.validate.amount ) {
			validateElementAmount( options );
		}
		
		// Validate: creditCard
		if ( options.validate.creditCard ) {
			validateElementCardNumber( options );
			validateElementCvv( options );
		}
		
		// Validate: email
		if ( options.validate.email ) {
			validateElementEmail( options );
		}
		
		// Validate: name
		if ( options.validate.name ) {
			validateElementFirstName( options );
			validateElementLastName( options );
		}
		
		// Validate: payment
		if ( options.validate.payment ) {
			validateElementPaymentMethod( options );
			validateElementPaymentSubmethod( options );
		
			// Validate: issuer_id
			if ( options.validate.issuerId ) {
				validateElementIssuerId( options );
			}
		}
		
		// The following validators are not ready:
	
	});
}

