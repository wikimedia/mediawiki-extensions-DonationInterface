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
				cache: true,
				fix: grunt.option( 'fix' )
			},
			all: [
				'{.,modules/**}/*.js{,on}',
				'!modules/js/{mailcheck,lg-hash,lightgallery}.js',
				'{adyen,amazon,braintree,dlocal,ingenico,paypal_ec}_gateway/**/*.js'
			]
		},
		stylelint: {
			all: [
				'{modules,gateway_forms}/{**/,}*.{css,less}',
				'{amazon,ingenico}_gateway/{**/,}*.{css,less}'
			]
		},
		banana: {
			options: {
				requireLowerCase: false
			},
			shared: 'gateway_common/i18n/*/',
			email: 'email_forms/i18n/',
			gateways: '{adyen,amazon,astropay,ingenico,paypal_ec}_gateway/i18n/'
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
