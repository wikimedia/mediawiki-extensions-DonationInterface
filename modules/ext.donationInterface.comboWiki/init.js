const Vue = require( 'vue' ),
	App = require( './components/App.vue' ),
	$container = $( '<div>' ).attr( 'id', 'combo-wiki-app' ),
	$vue = $( '<div>' ).appendTo( $container );

$( '#mw-content-text' ).append( $container );

const vueApp = Vue.createMwApp( App );
const { loadVariantModuleComponents } = require( './variantHelper.js' );

// Cross the mw.config boundary once, here — components below never touch mw.config directly.
const comboWikiConfig = mw.config.get( 'comboWiki' ) || { params: {} };
const params = Object.assign( {}, comboWikiConfig.params, {
	gateway: comboWikiConfig.gateway,
	language: comboWikiConfig.language,
	assets_path: mw.config.get( 'assets_path' ),
	wgDonationInterfaceCountries: mw.config.get( 'wgDonationInterfaceCountries' ),
	wgDonationInterfaceCurrencyRates: mw.config.get( 'wgDonationInterfaceCurrencyRates' ),
	DonationInterfaceThankYouPage: mw.config.get( 'DonationInterfaceThankYouPage' ),
	wgForbiddenViewType: mw.config.get( 'wgForbiddenViewType' ),
	wmf_token: mw.config.get( 'wmf_token' ),
	wgUserLanguage: mw.config.get( 'wgUserLanguage' ),
	adyenConfiguration: mw.config.get( 'adyenConfiguration' ),
	gravyConfiguration: mw.config.get( 'gravyConfiguration' ),
	script_path: mw.config.get( 'script_path' ),
	DonationInterfaceOtherWaysURL: mw.config.get( 'DonationInterfaceOtherWaysURL' ),
	wgDonationInterfaceMonthlyConvertAmounts: mw.config.get( 'wgDonationInterfaceMonthlyConvertAmounts' ),
	wgDonationInterfaceAmountRules: mw.config.get( 'wgDonationInterfaceAmountRules' ),
	DonationInterfaceNoDecimalCurrencies: mw.config.get( 'DonationInterfaceNoDecimalCurrencies' )
} );
vueApp.provide( 'params', params );

require( './api.js' ).init( params );

// load variant component
loadVariantModuleComponents( vueApp, comboWikiConfig.params.variant );

// Use this to prevent vue 3 default space trim.
vueApp.config.compilerOptions.whitespace = 'preserve';

vueApp.mount( $vue.get( 0 ) );
