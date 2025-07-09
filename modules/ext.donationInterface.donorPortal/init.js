const Vue = require( 'vue' ),
	App = require( './components/App.vue' ),
	$container = $( '<div>' ).attr( 'id', 'donor-portal-app' ),
	$vue = $( '<div>' ).appendTo( $container ),
	router = require( './router.js' );

// eslint-disable-next-line no-jquery/no-global-selector
$( '#mw-content-text' ).append( $container );

const vueApp = Vue.createMwApp( App );

vueApp.use( router );
// Use this to prevent vue 3 default space trim
vueApp.config.compilerOptions.whitespace = 'preserve';

vueApp.mount( $vue.get( 0 ) );
