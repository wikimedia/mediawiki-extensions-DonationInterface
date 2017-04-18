/*!
 * Grunt file
 *
 * @package DonationInterface
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),
		jshint: {
			options: {
				jshintrc: true
			},
			shared: [
				'*.js',
				'modules/*.js',
				'modules/js/*.js',
				'!modules/js/mailcheck.js'
			],
			tests: 'tests/*/*.js',
			gateways: '{adyen,amazon,globalcollect,paypal}_gateway/forms/**/*.js'
		},
		jscs: {
			shared: { src: '<%= jshint.shared %>' },
			tests: { src: '<%= jshint.tests %>' },
			gateways: { src: '<%= jshint.gateways %>' }
		},
		banana: {
			shared: 'gateway_common/i18n/*/',
			gateways: '{adyen,amazon,astropay,globalcollect,paypal}_gateway/i18n/'
		},
		watch: {
			files: [
				'.{jscsrc,jshintignore,jshintrc}',
				'<%= jshint.shared %>',
				'<%= jshint.tests %>',
				'<%= jshint.gateways %>'
			],
			tasks: 'test'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'.stylelintrc',
				'!node_modules/**'
			]
		},
		stylelint: {
			all: [
				'modules/css/*.css'
			]
		}
	} );

	grunt.registerTask( 'lint', [ 'jshint', 'jscs', 'jsonlint', 'banana', 'stylelint' ] );
	grunt.registerTask( 'test', [ 'lint' ] );
	grunt.registerTask( 'default', 'test' );
};
