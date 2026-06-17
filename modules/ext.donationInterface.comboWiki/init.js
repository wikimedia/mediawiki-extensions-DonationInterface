const Vue = require( 'vue' ),
	App = require( './components/App.vue' ),
	$container = $( '<div>' ).attr( 'id', 'combo-wiki-app' ),
	$vue = $( '<div>' ).appendTo( $container );

$( '#mw-content-text' ).append( $container );

const vueApp = Vue.createMwApp( App );

// Use this to prevent vue 3 default space trim.
vueApp.config.compilerOptions.whitespace = 'preserve';

vueApp.mount( $vue.get( 0 ) );
