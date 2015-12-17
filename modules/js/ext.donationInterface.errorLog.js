( function ( $, mw ) {
	window.onerror = function ( message, file, line, col, error ) {
		var postdata = {
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
			action: 'logPaymentsFormError',
			data: postdata,
			dataType: 'json',
			type: 'POST'
		} );
	};
} )( jQuery, mediaWiki );
