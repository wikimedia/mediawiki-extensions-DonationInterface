/**
 * Appeals shown on the right side of the donation form loaded from appeal=value
 * The appeal URL value is not case sensitive (e.g. ?appeal=MyAppeal matches myappeal),
 * so keys in APPEALS must be all lowercase
 * If the name isn't listed here the form is centered without showing an appeal
 *
 * To add an appeal: create its component in components/appeals/, add it below,
 * and list it in the extension.json packageFiles for ext.donationInterface.comboWiki
 *
 */
const APPEALS = {
	wydg: () => require( './components/appeals/WydgAppeal.vue' ),
	mission: () => require( './components/appeals/MissionAppeal.vue' ),
	hero: () => require( './components/appeals/HeroAppeal.vue' )
};

/**
 * @param {string|null} name The appeal URL parameter
 * @return {Object|null} The appeal component, or null for none
 */
function getAppeal( name ) {
	const key = typeof name === 'string' ? name.toLowerCase() : '';
	return Object.prototype.hasOwnProperty.call( APPEALS, key ) ? APPEALS[ key ]() : null;
}

module.exports = { APPEALS, getAppeal };
