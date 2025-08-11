<?php

namespace MediaWiki\Extension\DonationInterface\DonorPortal;

use Config;
use MediaWiki\MainConfigNames;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FilePath;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

/**
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	ResourceLoaderRegisterModulesHook
{
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$resourceLoader->register( [
			'vue-router' => [
			'packageFiles' => [
				[
					'name' => 'resources/lib/vue-router/vue-router.js',
					'callback' => static function ( Context $context, Config $config ) {
						// Use the development version if development mode is enabled, or if we're in debug mode
						$file = $config->get( MainConfigNames::VueDevelopmentMode ) || $context->getDebug() ?
							'resources/lib/vue-router/vue-router.global.js' :
							'resources/lib/vue-router/vue-router.global.prod.js';
						// The file shipped by Vuex does var Vuex = ...;, but doesn't export it
						// Add module.exports = Vuex; programmatically, and import Vue
						return "var Vue=require('vue');" .
							file_get_contents( MW_INSTALL_PATH . '/extensions/DonationInterface' . "/$file" ) .
							';module.exports=VueRouter;';
					},
					'versionCallback' => static function ( Context $context, Config $config ) {
						$file = $config->get( MainConfigNames::VueDevelopmentMode ) || $context->getDebug() ?
							'resources/lib/vue-router/vue-router.global.js' :
							'resources/lib/vue-router/vue-router.global.prod.js';
						return new FilePath( $file,
						MW_INSTALL_PATH . '/extensions/DonationInterface',
						MW_INSTALL_PATH . '/extensions/DonationInterface' );
					}
				],
			],
			"dependencies" => [ 'vue' ],
			] ] );
	}
}
