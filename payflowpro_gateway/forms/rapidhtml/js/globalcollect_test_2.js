// make HTML5 placeholders work in non supportive browsers
$("input[placeholder]").each(function() {
	if($(this).val()=="") {
		$(this).addClass('hasplaceholder');
		$(this).val($(this).attr("placeholder"));
		$(this).focus(function() {
			if($(this).val()==$(this).attr("placeholder")) $(this).val("");
			$(this).removeClass('hasplaceholder');
		});
		$(this).blur(function() {
			if($(this).val()=="") {
				$(this).addClass('hasplaceholder');
				$(this).val($(this).attr("placeholder"));
			}
		});
	}
});

// clear the placeholder values on form submit
$('form').submit(function(evt){
	$('input[placeholder]').each(function(){
		if($(this).attr("placeholder") == $(this).val()) {$(this).val('');}
	});
});

window.formCheck = function( ccform ) {
	var msg = [ 'EmailAdd', 'Fname', 'Lname', 'Street', 'City', 'Zip' ];

	var fields = ["emailAdd","fname","lname","street","city","zip" ],
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
	if (document.getElementById('street').value == '$street') {
		output += payflowproGatewayErrorMsgJs + ' street address.\r\n';
	}
	if (document.getElementById('city').value == '$city') {
		output += payflowproGatewayErrorMsgJs + ' city.\r\n';
	}
	if (document.getElementById('zip').value == '$zip') {
		output += payflowproGatewayErrorMsgJs + ' zip code.\r\n';
	}
	
	var stateField = document.getElementById( 'state' );
	if( stateField.options[stateField.selectedIndex].value == '' ) {
		output += payflowproGatewayErrorMsgJs + ' ' + window['payflowproGatewayErrorMsgState'] + '.\r\n';
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
}
