( function ( $, mw ) {
	var apiAction = mw.config.get( 'ClientErrorLogAction', 'logPaymentsFormError' ),
		ignorePatterns = mw.config.get( 'wgDonationInterfaceClientErrorLogIgnorePatterns' ),
		ignoreRegexes = [];
	window.onerror = function ( message, file, line, col, error ) {
		var i, postdata;
		for ( i = 0; i < ignorePatterns.length; i++ ) {
			if ( ignoreRegexes.length <= i ) {
				// turn patterns into regexes the first time through
				ignoreRegexes[ i ] = new RegExp( ignorePatterns[ i ] );
			}
			if ( message.match( ignoreRegexes[ i ] ) ) {
				return;
			}
		}
		postdata = {
			action: apiAction,
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
