const scriptPromises = {};

/**
 * Removes a script added by loadScript so a later call injects it again.
 *
 * @param {string} src
 */
function unloadScript( src ) {
	delete scriptPromises[ src ];
	Array.prototype.forEach.call(
		document.body.querySelectorAll( 'script[src="' + src + '"]' ),
		( node ) => node.remove()
	);
}

/**
 * Injects a third-party script once and resolves when it has loaded.
 * Repeated calls for the same src share the same promise.
 *
 * @param {string} src
 * @return {Promise}
 */
function loadScript( src ) {
	if ( !scriptPromises[ src ] ) {
		scriptPromises[ src ] = new Promise( ( resolve, reject ) => {
			const node = document.createElement( 'script' );
			node.src = src;
			node.onload = resolve;
			node.onerror = ( e ) => {
				// Allow a retry after a network failure
				unloadScript( src );
				reject( e );
			};
			document.body.append( node );
		} );
	}
	return scriptPromises[ src ];
}

module.exports = { loadScript, unloadScript };
