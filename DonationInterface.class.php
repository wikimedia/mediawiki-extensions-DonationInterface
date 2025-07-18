<?php

use SmashPig\Core\Context;
use SmashPig\Core\GlobalConfiguration;
use SmashPig\Core\ProviderConfiguration;

class DonationInterface {
	/**
	 * Executed after processing extension.json
	 */
	public static function registerExtension() {
		global $wgDonationInterfaceTest,
			$wgDonationInterfaceTemplate,
			$wgDonationInterfaceErrorTemplate,
			$wgDonationInterfaceMessageSourceType,
			$IP;

		// Test mode (not for production!)
		// Set it if not defined
		if ( !isset( $wgDonationInterfaceTest ) || $wgDonationInterfaceTest !== true ) {
			$wgDonationInterfaceTest = false;
		}

		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			require_once __DIR__ . '/tests/phpunit/TestConfiguration.php';
		}

		/**
		 * Default top-level template file.
		 */
		$wgDonationInterfaceTemplate = __DIR__ . '/gateway_forms/mustache/index.html.mustache';

		/**
		 * Default top-level error template file.
		 */
		$wgDonationInterfaceErrorTemplate = __DIR__ . '/gateway_forms/mustache/error_form.html.mustache';

		// Initialize the SmashPig context
		$spConfig = GlobalConfiguration::create();
		Context::init( $spConfig );
		$context = Context::get();
		$context->setSourceName( 'DonationInterface' );
		$context->setSourceType( $wgDonationInterfaceMessageSourceType );
		$context->setVersionFromFile( "$IP/.version-stamp" );
	}

	public static function getAdapterClassForGateway( string $gateway ): string {
		global $wgDonationInterfaceGatewayAdapters;
		if ( !array_key_exists( $gateway, $wgDonationInterfaceGatewayAdapters ) ) {
			throw new OutOfRangeException( "No adapter configured for $gateway" );
		}
		return $wgDonationInterfaceGatewayAdapters[$gateway];
	}

	/**
	 * Initialize SmashPig context and return configuration object
	 *
	 * @param string $provider
	 * @return ProviderConfiguration
	 */
	public static function setSmashPigProvider( $provider ) {
		$ctx = Context::get();
		$spConfig = ProviderConfiguration::createForProvider(
			$provider,
			$ctx->getGlobalConfiguration()
		);
		// FIXME: should set a logger prefix here, but we've got a chicken
		// and egg problem with the Gateway constructor
		$ctx->setProviderConfiguration( $spConfig );
		return $spConfig;
	}

	/**
	 * Register es-419 as a language supported by this extension but not by
	 * MediaWiki core. Handles Language::onGetMessagesFileName hook called in
	 * LanguageNameUtils::getMessagesFileName
	 *
	 * @param string $code language code
	 * @param string &$file path of Messages file as found by MediaWiki core
	 */
	public static function onGetMessagesFileName( $code, &$file ) {
		if ( $code === 'es-419' ) {
			$file = __DIR__ . DIRECTORY_SEPARATOR . 'gateway_common' . DIRECTORY_SEPARATOR .
				'messages' . DIRECTORY_SEPARATOR . 'MessagesEs_419.php';
		}
	}
}
