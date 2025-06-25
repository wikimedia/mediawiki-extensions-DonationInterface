const Home = require( './views/Home.vue' ),
	Login = require( './views/Login.vue' ),
	PauseDonations = require( './views/PauseDonations.vue' ),
	CancelDonations = require( './views/CancelDonations.vue' );

module.exports = [
  { path: '/', component: Home },
  { path: '/login', component: Login },
  { path: '/pause-donations', component: PauseDonations },
  { path: '/cancel-donations', component: CancelDonations }
];
