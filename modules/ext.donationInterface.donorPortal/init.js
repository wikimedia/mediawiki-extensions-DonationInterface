const Vue = require( 'vue' ),
	{ createWebHashHistory, createRouter } = require( 'vue-router' ),
	App = require( './components/App.vue' ),
	$container = $( '<div>' ).attr( 'id', 'donor-portal-app' ),
	$vue = $( '<div>' ).appendTo( $container ),
	routes = require( './routes.js' );

// eslint-disable-next-line no-jquery/no-global-selector
$( '#bodyContent' ).append( $container );

const vueApp = Vue.createMwApp( App );
const router = createRouter( {
	history: createWebHashHistory(),
	routes
} );
vueApp.use( router );
// Use this to prevent vue 3 default space trim
vueApp.config.compilerOptions.whitespace = 'preserve';

vueApp.mount( $vue.get( 0 ) );
