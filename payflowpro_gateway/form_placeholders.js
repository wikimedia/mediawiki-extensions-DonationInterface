//<![CDATA[
( function( $ ) {
	$(document).ready(function() {
		if ( $( '#fname' ).val() == '') {
			$( '#fname' ).css( 'color', '#999999' );
			$( '#fname' ).val( mw.msg( 'donate_interface-donor-fname' ) );
		}
		if ( $( '#lname' ).val() == '') {
			$( '#lname' ).css( 'color', '#999999' );
			$( '#lname' ).val( mw.msg( 'donate_interface-donor-lname' ) );
		}
	});
})(jQuery);

window.formCheck = function( ccform ) {
	var fields = ['emailAdd','fname','lname','street','city','zip','card_num','cvv' ],
		numFields = fields.length,
		i,
		output = '',
		currField = '';

	for( i = 0; i < numFields; i++ ) {
		if( document.getElementById( fields[i] ).value == '' ) {
			currField = mw.msg( 'donate_interface-error-msg-' + fields[i] );
			output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + currField + '.\r\n';
		}
	}
	
	if (document.getElementById('fname').value == '$first') {
		output += mw.msg( 'donate_interface-error-msg-js' ) + ' first name.\r\n';
	}
	if (document.getElementById('lname').value == '$last') {
		output += mw.msg( 'donate_interface-error-msg-js' ) + ' last name.\r\n';
	}
	var countryField = document.getElementById( 'country' );
	if( countryField.options[countryField.selectedIndex].value == '' ) {
		output += mw.msg( 'donate_interface-error-msg-js' ) + ' ' + mw.msg( 'donate_interface-error-msg-country' ) + '.\r\n';
	}

	// validate email address
	var apos = document.payment.emailAdd.value.indexOf("@");
	var dotpos = document.payment.emailAdd.value.lastIndexOf(".");

	if( apos < 1 || dotpos-apos < 2 ) {
		output += mw.msg( 'donate_interface-error-msg-email' );
	}
	
	if( output ) {
		alert( output );
		return false;
	} else {
		return true;
	}
}
//]]>
