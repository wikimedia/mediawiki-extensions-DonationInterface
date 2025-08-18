const { createWebHashHistory, createRouter } = require( 'vue-router' ),
	Home = require( './views/Home.vue' ),
	Login = require( './views/LoginView.vue' ),
	PauseDonations = require( './views/PauseDonations.vue' ),
	CancelDonations = require( './views/CancelDonations.vue' ),
	AnnualConversion = require( './views/AnnualConversion.vue' ),
	AmountDowngrade = require( './views/AmountDowngrade.vue' );

const routes = [
  { path: '/', component: Home, name: 'Home' },
  { path: '/login', component: Login, name: 'Login' },
  { path: '/pause-donations/:id', component: PauseDonations, name: 'PauseDonations' },
  { path: '/cancel-donations/:id', component: CancelDonations, name: 'CancelDonations' },
  { path: '/annual-conversion/:id', component: AnnualConversion, name: 'AnnualConversion' },
  { path: '/amount-downgrade/:id', component: AmountDowngrade, name: 'AmountDowngrade' }
];

const router = createRouter( {
	history: createWebHashHistory(),
	routes
} );

router.beforeEach( async ( to, from ) => {
	// check if donor has valid checksum and avoid infinite redirect
	if ( mw.config.get( 'showRequestNewChecksumModal' ) ) {
		if ( to.name !== 'Login' ) {
			return {
				name: 'Login'
			};
		}
	} else {
		// do not allow request of new checksum if current checksum is valid
		if ( to.name === 'Login' ) {
			return {
				name: 'Home'
			};
		}
	}
} );

module.exports = exports = router;
