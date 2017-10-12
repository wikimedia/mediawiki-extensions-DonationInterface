( function ( $, mw ) {
	window.onerror = function ( message, file, line, col, error ) {
		var postdata = {
			action: 'logPaymentsFormError',
			message: message,
			file: file,
			line: line,
			col: col,
			userAgent: navigator.userAgent
		};
		if ( error && error.stack ) {
			postdata.stack = error.stack;
		}
		$.ajax( {
			url: mw.util.wikiScript( 'api' ),
			data: postdata,
			dataType: 'json',
			type: 'POST'
		} );
	};
} )( jQuery, mediaWiki );
