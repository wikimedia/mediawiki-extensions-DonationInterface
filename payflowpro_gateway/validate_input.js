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

function getIfSessionSet() {
	sajax_do_call( 'efPayflowGatewayCheckSession', [], checkSession );
}

function clearField( field, defaultValue ) {
	if (field.value == defaultValue) {
		field.value = '';
		field.style.color = 'black';
	}
}

function switchToPayPal() {
	document.getElementById('payflow-table-cc').style.display = 'none';
	document.getElementById('payflowpro_gateway-form-submit').style.display = 'none';
	document.getElementById('payflowpro_gateway-form-submit-paypal').style.display = 'block';
}
function switchToCreditCard() {
	document.getElementById('payflow-table-cc').style.display = 'table';
	document.getElementById('payflowpro_gateway-form-submit').style.display = 'block';
	document.getElementById('payflowpro_gateway-form-submit-paypal').style.display = 'none';
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
	var apos = document.payment.emailAdd.value.indexOf("@");
	var dotpos = document.payment.emailAdd.value.lastIndexOf(".");

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
		// weird hack!!!!!! for some reasondoing just ccform.submit() throws an error....
		$j(ccform).submit();
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
