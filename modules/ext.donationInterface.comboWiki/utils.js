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

/**
 * Overrides what a payment method submits with what the donor entered in the details form.
 *
 * A wallet (Apple Pay, Google Pay) submits the donor's billing address too. Where the donor
 * also entered a field, their entry wins: e.g. the state code picked from the list, where
 * Apple Pay may send free text such as "New South Wales". A field the donor left empty keeps
 * the payment method's value.
 *
 * @param {Object} paymentMethodData What the payment method submits, e.g. a wallet's token and address
 * @param {Object} formEntries What the donor entered in the details form, keyed by server field name
 * @return {Object} paymentMethodData, with each non-empty form entry in place of its value
 */
function overrideWithFormEntries( paymentMethodData, formEntries ) {
	const result = Object.assign( {}, paymentMethodData );
	for ( const [ fieldName, enteredValue ] of Object.entries( formEntries ) ) {
		if ( enteredValue !== undefined && enteredValue !== null && enteredValue !== '' ) {
			result[ fieldName ] = enteredValue;
		}
	}
	return result;
}

module.exports = { loadScript, unloadScript, overrideWithFormEntries };
