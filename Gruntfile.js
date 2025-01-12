/*!
 * Grunt file
 *
 * @package DonationInterface
 */

/* eslint-env node */
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );
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
				'{adyen,amazon,braintree,dlocal,ingenico,paypal_ec,gravy}_gateway/**/*.js'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'{modules,gateway_forms}/{**/,}*.{css,less}',
				'{amazon,ingenico}_gateway/{**/,}*.{css,less}'
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
