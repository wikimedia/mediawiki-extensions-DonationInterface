//<![CDATA[
function loadPlaceholders() {
	var fname = document.getElementById('fname');
	var lname = document.getElementById('lname');
	var amountOther = document.getElementById('amountOther');
	if (fname.value == '') {
		fname.style.color = '#999999';
		fname.value = mw.msg( 'payflowpro_gateway-donor-fname' );
	}
	if (lname.value == '') {
		lname.style.color = '#999999';
		lname.value = mw.msg( 'payflowpro_gateway-donor-lname' );
	}
}
addEvent( window, 'load', loadPlaceholders );

function formCheck( ccform ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'Zip', 'CardNum', 'Cvv' ];

	var fields = ["emailAdd","fname","lname","street","city","zip","card_num","cvv" ],
		numFields = fields.length,
		i,
		output = '',
		currField = '';

	for( i = 0; i < numFields; i++ ) {
		if( document.getElementById( fields[i] ).value == '' ) {
			currField = window['payflowproGatewayErrorMsg'+ msg[i]];
			output += payflowproGatewayErrorMsgJs + ' ' + currField + '.\\r\\n';
		}
	}
	
	if (document.getElementById('fname').value == '$first') {
		output += payflowproGatewayErrorMsgJs + ' first name.\\r\\n';
	}
	if (document.getElementById('lname').value == '$last') {
		output += payflowproGatewayErrorMsgJs + ' last name.\\r\\n';
	}
	var countryField = document.getElementById( 'country' );
	if( countryField.options[countryField.selectedIndex].value == '' ) {
		output += payflowproGatewayErrorMsgJs + ' ' + window['payflowproGatewayErrorMsgCountry'] + '.\\r\\n';
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