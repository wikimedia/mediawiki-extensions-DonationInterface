const Vue = require( 'vue' );

/**
 * Maps a variant value to the ResourceLoader module that provides its
 * fields component and the tag name that component should be globally registered under.
 *
 * @type {Object.<string, {module: string, component_name: string}>}
 */
const VARIANT_MODULE_MAPPER = {
	smsOptin: {
		module: 'ext.donationInterface.combowiki.smsoptin',
		component_name: 'sms-optin'
	}
};

/**
 * Registers the Vue component for the given variant as a global component on the app,
 * loading its ResourceLoader module lazily on first render. Does nothing if the variant
 * is falsy or not present in VARIANT_MODULE_MAPPER.
 *
 * @param {Object} app Vue application instance, as returned by Vue.createMwApp().
 * @param {string} variant Variant key to look up in VARIANT_MODULE_MAPPER, e.g. 'smsOptin'.
 */
function loadVariantModuleComponents( app, variant ) {
	if ( !variant || !VARIANT_MODULE_MAPPER[ variant ] ) {
		return;
	}

	const variant_module = VARIANT_MODULE_MAPPER[ variant ].module;

	app.component(
		getVariantComponentName( variant ),
		Vue.defineAsyncComponent(
			() => mw.loader
				.using( variant_module )
				.then(
					( moduleRequire ) => moduleRequire( variant_module )
				)
		) );
}

/**
 * Returns the variant component_name value if present
 *
 * @param {string} variant
 * @return
 */

function getVariantComponentName( variant ) {
	if ( !VARIANT_MODULE_MAPPER[ variant ] ) {
		return '';
	}
	return VARIANT_MODULE_MAPPER[ variant ].component_name;
}
module.exports = {
	VARIANT_MODULE_MAPPER,
	getVariantComponentName,
	loadVariantModuleComponents
};
