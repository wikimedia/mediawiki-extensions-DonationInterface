/*!
 * Grunt file
 *
 * @package DonationInterface
 */

/* eslint-env node */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				reportUnusedDisableDirectives: true,
				extensions: [ '.js', '.json' ],
				cache: true
			},
			shared: [
				'{.,modules/**}/*.js{,on}',
				'!modules/js/{mailcheck,lg-hash,lightgallery}.js'
			],
			gateways: '{adyen,amazon,globalcollect,ingenico,paypal}_gateway/**/*.js'
		},
		stylelint: {
			options: {
				syntax: 'less'
			},
			shared: '{modules,gateway_forms}/{**/,}*.{css,less}',
			gateways: '{amazon,ingenico}_gateway/{**/,}*.{css,less}'
		},
		banana: {
			options: {
				requireLowerCase: false
			},
			shared: 'gateway_common/i18n/*/',
			gateways: '{adyen,amazon,astropay,globalcollect,paypal}_gateway/i18n/'
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
