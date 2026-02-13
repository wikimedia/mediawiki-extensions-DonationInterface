const { createWebHashHistory, createRouter } = require( 'vue-router' ),
	Home = require( './views/Home.vue' ),
	Login = require( './views/LoginView.vue' ),
	PauseDonations = require( './views/PauseDonations.vue' ),
	CancelDonations = require( './views/CancelDonations.vue' ),
	UpdateDonations = require( './views/UpdateDonations.vue' ),
	AnnualConversion = require( './views/AnnualConversion.vue' ),
	AmountDowngrade = require( './views/AmountDowngrade.vue' ),
	wmfParams = new URLSearchParams( window.location.search );

for ( const k of wmfParams.keys() ) {
	if ( !k.startsWith( 'wmf_' ) ) {
		wmfParams.delete( k );
	}
}
const wmfSuffix = ( wmfParams.size === 0 ? '' : '?' + wmfParams.toString() );

function logNavigation( to ) {
	const donorData = mw.config.get( 'donorData' );
	navigator.sendBeacon( '/beacon/donor_portal/' + donorData.contact_id + to.fullPath + wmfSuffix );
}

const routes = [
  { path: '/', component: Home, name: 'Home' },
  { path: '/login', component: Login, name: 'Login' },
  { path: '/pause-donations/:id', component: PauseDonations, name: 'PauseDonations', beforeEnter: logNavigation },
  { path: '/cancel-donations/:id', component: CancelDonations, name: 'CancelDonations', beforeEnter: logNavigation },
  { path: '/update-donations/:id', component: UpdateDonations, name: 'UpdateDonations', beforeEnter: logNavigation },
  { path: '/annual-conversion/:id', component: AnnualConversion, name: 'AnnualConversion', beforeEnter: logNavigation },
  { path: '/amount-downgrade/:id', component: AmountDowngrade, name: 'AmountDowngrade', beforeEnter: logNavigation },
  { path: '/annual-conversion/:id/save', component: AnnualConversion, name: 'AnnualConversionSave' },
  { path: '/amount-downgrade/:id/save', component: AmountDowngrade, name: 'AmountDowngradeSave' }
];

const router = createRouter( {
	history: createWebHashHistory(),
	routes
} );

router.beforeEach( async ( to, from ) => {
	const donorData = mw.config.get( 'donorData' );
	// check if donor has valid checksum and avoid infinite redirect
	if ( mw.config.get( 'showRequestNewChecksumModal' ) || !donorData || donorData.showLogin ) {
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
