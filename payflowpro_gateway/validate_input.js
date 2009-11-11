//<![CDATA[

function validate_form( form ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'State', 'Zip', 'CardNum', 'Cvv' ];

	var fields = ["emailAdd","fname","lname","street","city","state","zip","card_num","cvv"],
		numFields = fields.length,
		i,
		output = '',
		currField = '';

	for( i = 0; i < numFields; i++ ) {
		if( document.getElementById( fields[i] ).value == '' ) {
			currField = window['payflowproGatewayErrorMsg'+ msg[i]];
			output += payflowproGatewayErrorMsgJs + currField + '.\r\n';
		}
	}

	//disable states if country is not US
	if ( document.payment.country.value != '840' ) {
		document.payment.state.disabled = true;
		document.payment.state.value = 'XX';
	} else {
		document.payment.state.disabled = false;
		document.payment.state.value = document.payment.state.value;
	}

	// validate email address
	var apos = form.emailAdd.value.indexOf("@");
	var dotpos = form.emailAdd.value.lastIndexOf(".");

	if( apos < 1 || dotpos-apos < 2 ) {
		output += payflowproGatewayErrorMsgEmail;
	}

	if( output ) {
		alert( output );
		return false;
	}  

	return true;
}

function disableStates( form ) {
	
	if ( document.payment.country.value != '840' ) {
		document.payment.state.disabled = true;
		document.payment.state.value = 'XX';
	} else {
		document.payment.state.disabled = false;
		document.payment.state.value = '';
	}
	
	return true;
}

//]]>