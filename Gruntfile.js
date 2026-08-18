/*!
 * Grunt file
 *
 * @package DonationInterface
 */

/* eslint-env node */
module.exports = function ( grunt ) {
	const conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true,
				fix: grunt.option( 'fix' )
			},
			main: [
				'{.,modules/**}/*.js{,on}',
				'!modules/js/{mailcheck,lg-hash,lightgallery}.js'
			],
			donorPortal: [
				'modules/ext.donationInterface.donorPortal/**/*.{vue,js}'
			],
			comboWiki: [
				'modules/ext.donationInterface.comboWiki/**/*.{vue,js}'
			],
			gateways: [
				'{adyen,amazon,braintree,dlocal,ingenico,paypal_ec,gravy}_gateway/**/*.js'
			],
			tests: [
				'tests/**/*.js'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			main: [
				'{modules,gateway_forms}/{**/,}*.{css,less}'
			],
			gateways: [
				'{adyen,amazon,braintree,dlocal,ingenico,paypal_ec,gravy}_gateway/{**/,}*.{css,less}'
			]
		},
		banana: Object.assign(
			{
				options: {
					requireLowerCase: false
				}
			},
			conf.MessagesDirs
		)
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
