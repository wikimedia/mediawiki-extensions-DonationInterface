//<![CDATA[
( function( $ ) {
	$(document).ready(function() {
		if ( $( '#fname' ).val() == '') {
			$( '#fname' ).css( 'color', '#999999' );
			$( '#fname' ).val( mw.msg( 'payflowpro_gateway-donor-fname' ) );
		}
		if ( $( '#lname' ).val() == '') {
			$( '#lname' ).css( 'color', '#999999' );
			$( '#lname' ).val( mw.msg( 'payflowpro_gateway-donor-lname' ) );
		}
	});
})(jQuery);

window.formCheck = function( ccform ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'Zip', 'CardNum', 'Cvv' ];

	var fields = ["emailAdd","fname","lname","street","city","zip","card_num","cvv" ],
		numFields = fields.length,
		i,
		output = '',
		currField = '';

	for( i = 0; i < numFields; i++ ) {
		if( document.getElementById( fields[i] ).value == '' ) {
			currField = window['payflowproGatewayErrorMsg'+ msg[i]];
			output += payflowproGatewayErrorMsgJs + ' ' + currField + '.\r\n';
		}
	}
	
	if (document.getElementById('fname').value == '$first') {
		output += payflowproGatewayErrorMsgJs + ' first name.\r\n';
	}
	if (document.getElementById('lname').value == '$last') {
		output += payflowproGatewayErrorMsgJs + ' last name.\r\n';
	}
	var countryField = document.getElementById( 'country' );
	if( countryField.options[countryField.selectedIndex].value == '' ) {
		output += payflowproGatewayErrorMsgJs + ' ' + window['payflowproGatewayErrorMsgCountry'] + '.\r\n';
	}

	// validate email address
	var apos = document.payment.emailAdd.value.indexOf("@");
	var dotpos = document.payment.emailAdd.value.lastIndexOf(".");

	if( apos < 1 || dotpos-apos < 2 ) {
		output += payflowproGatewayErrorMsgEmail;
	}
	
	if( output ) {
		alert( output );
		return false;
	} else {
		return true;
	}
}
//]]>