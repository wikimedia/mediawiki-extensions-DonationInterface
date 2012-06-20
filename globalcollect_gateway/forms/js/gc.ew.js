
$( document ).ready( function () {

	$( "#bt-continueBtn" ).live( "click", function() {
		if ( validateAmount() ) { //&& validate_personal( document.paypalcontribution ) ) {
			document.paypalcontribution.action = actionURL;
			document.paypalcontribution.submit();
		}
	} );

} );

