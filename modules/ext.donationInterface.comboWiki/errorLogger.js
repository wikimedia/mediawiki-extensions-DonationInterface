/**
 * Catch-all client error logging
 *
 * Sends errors from the sources below to the server log via logClientError.
 * - global.error: uncaught errors, with file, line, column and stack
 * - error.vue: errors in Vue components
 * - resourceloader.exception: errors thrown while a ResourceLoader module runs
 * - unhandledrejection (browser event): rejected promises with no catch handler
 */
const { logClientError } = require( './api.js' );

// Stop a page that errors in a loop from flooding the API
const MAX_REPORTS = 10;

/**
 * @param {string[]} ignorePatterns Regex strings; errors whose message matches are not logged
 */
function install( ignorePatterns ) {
	const ignoreRegexes = ( ignorePatterns || [] ).map( ( pattern ) => new RegExp( pattern ) );
	let reportCount = 0;

	function report( details ) {
		const message = String( details.message || '' );
		if ( reportCount >= MAX_REPORTS || ignoreRegexes.some( ( re ) => re.test( message ) ) ) {
			return;
		}
		reportCount++;
		logClientError( Object.assign( {}, details, { message } ) );
	}

	mw.trackSubscribe( 'global.error', ( topic, data ) => report( {
		message: data.errorMessage,
		file: data.url,
		line: data.lineNumber,
		col: data.columnNumber,
		stack: data.stackTrace
	} ) );

	mw.trackSubscribe( 'error.vue', ( topic, error ) => report( {
		message: ( error && error.message ) || error,
		file: 'vue',
		stack: error && error.stack
	} ) );

	mw.trackSubscribe( 'resourceloader.exception', ( topic, data ) => report( {
		message: data.exception && data.exception.message,
		file: data.module,
		stack: data.exception && data.exception.stack
	} ) );

	window.addEventListener( 'unhandledrejection', ( event ) => {
		const reason = event.reason;
		let reasonMessage;
		if ( reason && reason.message ) {
			reasonMessage = reason.message;
		} else {
			try {
				reasonMessage = JSON.stringify( reason );
			} catch ( e ) {
				reasonMessage = String( reason );
			}
		}
		report( {
			message: 'Unhandled rejection: ' + reasonMessage,
			stack: reason && reason.stack
		} );
	} );
}

module.exports = { install };
