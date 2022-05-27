( function ( $, mw ) {
	$(function (){
		$( '.submethods' ).after(
			$( '<p id="redirect-explanation">' + mw.msg( 'donate_interface-redirect-explanation' ) + '</p>' )
		);

		if ( $('#country').val() === 'IN' ) {
			$( '#fiscal_number' ).after(
				$( '<input type="hidden" value="Mumbai" name="city" id="city">' +
					'<p style="font-size: 10px">' + mw.msg( 'donate_interface-donor-fiscal_number-explain-in' ) +
					'</p>' )
			);
		}
	});
} )( jQuery, mediaWiki );
