const { createWebHashHistory, createRouter } = require( 'vue-router' ),
	Home = require( './views/Home.vue' );

const routes = [
	{ path: '/', component: Home, name: 'Home' }
];

const router = createRouter( {
	history: createWebHashHistory(),
	routes
} );

// TODO: wire funnel analytics (frequency/payment-method selected, etc.) here
// once the step views exist. Unlike DonorPortal there is no checksum/login
// guard since ComboWiki has no donor authentication.

module.exports = exports = router;
