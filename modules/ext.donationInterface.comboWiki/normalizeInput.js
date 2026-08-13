const normalizeInput = {
	// Uses native trimStart when available, falling back to a regex for older browsers.
	// using the stripLeadingWhitespace because of the es-x aggressive mode
	// (https://eslint-community.github.io/eslint-plugin-es-x/#the-aggressive-mode)
	// on wikimedia eslint config
	// https://github.com/wikimedia/eslint-config-wikimedia/blob/v0.32.4/common.json#L9
	stripLeadingWhitespace: ( raw ) => {
		const str = raw || '';
		if ( typeof str.trimStart === 'function' ) {
			return str.trimStart();
		}
		return str.replace( /^\s+/, '' );
	}
};

module.exports = exports = normalizeInput;
