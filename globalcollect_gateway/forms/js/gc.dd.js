
$( document ).ready( function () {

    $( "#bt-continueBtn" ).live( "click", function() {
        if ( validateAmount() && validate_personal( document.payment ) ) {
            document.payment.action = actionURL;
            document.payment.submit();
        }
    } );

} );

