const Vue = require( 'vue' );

/**
 * Maps a variant value to the ResourceLoader module that provides its fields component.
 *
 * @type {Object.<string, {module: string}>}
 */
const VARIANT_MODULE_MAPPER = {
	smsOptin: {
		module: 'ext.donationInterface.combowiki.smsoptin'
	}
};

/**
 * Returns the Vue components for the given variant, keyed by variant name, each one
 * loading its ResourceLoader module lazily on first render. Returns an empty object if
 * the variant is falsy or not present in VARIANT_MODULE_MAPPER.
 *
 * The components are provided to the app rather than registered by tag name, so
 * templates render them with <component :is> and Vue never tries to resolve a tag
 * that isn't registered on forms without the variant.
 *
 * @param {string} variant Variant key to look up in VARIANT_MODULE_MAPPER, e.g. 'smsOptin'.
 * @return {Object.<string, Object>}
 */
function getVariantComponents( variant ) {
	if ( !variant || !VARIANT_MODULE_MAPPER[ variant ] ) {
		return {};
	}

	const variant_module = VARIANT_MODULE_MAPPER[ variant ].module;

	return {
		[ variant ]: Vue.defineAsyncComponent(
			() => mw.loader
				.using( variant_module )
				.then(
					( moduleRequire ) => moduleRequire( variant_module )
				)
		)
	};
}

module.exports = {
	VARIANT_MODULE_MAPPER,
	getVariantComponents
};
