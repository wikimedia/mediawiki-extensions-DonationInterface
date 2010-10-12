//<![CDATA[
function addEvent(obj, evType, fn){ 
	if (obj.addEventListener){ 
		obj.addEventListener(evType, fn, false); 
		return true; 
	} else if (obj.attachEvent){ 
		var r = obj.attachEvent("on"+evType, fn); 
		return r; 
	} else { 
		return false; 
	} 
}

function loadPlaceholders() {
	var fname = document.getElementById('fname');
	var lname = document.getElementById('lname');
	var amountOther = document.getElementById('amountOther');
	if (fname.value == '') {
		fname.style.color = '#999999';
		fname.value = 'First';
	}
	if (lname.value == '') {
		lname.style.color = '#999999';
		lname.value = 'Last';
	}
	if (amountOther.value == '') {
		amountOther.style.color = '#999999';
		amountOther.value = 'Other';
	}
}

//addEvent( window, 'load', loadPlaceholders );

function getIfSessionSet() {
	sajax_do_call( 'efPayflowGatewayCheckSession', [], checkSession );
}

addEvent( window, 'load', getIfSessionSet );

function checkSession( request ) {
	if ( request.responseText == "no" ) {
		window.location = document.location.href;
	}
}

function clearField( field, defaultValue ) {
	if (field.value == defaultValue) {
		field.value = '';
		field.style.color = 'black';
	}
}

function validate_form( form ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'State', 'Zip', 'CardNum', 'Cvv' ];

	var fields = ["emailAdd","fname","lname","street","city","state","zip","card_num","cvv" ],
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
	
	//set state to "outside us"
	if ( document.payment.country.value != '840' ) {
			document.payment.state.value = 'XX';
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

function submit_form( ccform ) {
	if ( validate_form( ccform )) {
		ccform.submit();
	}
	return true;
}

function disableStates( form ) {

		if ( document.payment.country.value != '840' ) {
			document.payment.state.value = 'XX';
		} else {
			document.payment.state.value = 'YY';
		}

		return true;
}

var cvv;

function PopupCVV() {
	cvv = window.open("", 'cvvhelp','scrollbars=yes,resizable=yes,width=600,height=400,left=200,top=100');
	cvv.document.write( payflowproGatewayCVVExplain ); 
	cvv.focus();
}

function CloseCVV() {
	if (cvv) {
		if (!cvv.closed) cvv.close();
		cvv = null;
	}
}

window.onfocus = CloseCVV; 
//]]>
