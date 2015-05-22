/*global actionURL:true, validateAmount:true, displayErrors:true, validate_form:true*/
$( document ).ready( function () {
	displayErrors();

	$( '#bt-continueBtn' ).on( 'click', function () {
		if ( validateAmount() && validate_form( document.payment ) ) {
			document.payment.action = actionURL;
			document.payment.submit();
		}
	} );

} );
