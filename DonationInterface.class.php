<?php

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;

class DonationInterface {
	/**
	 * Executed after processing extension.json
	 */
	public static function registerExtension() {
		global $wgDonationInterfaceTestMode,
			$wgDonationInterfaceTemplate,
			$wgDonationInterfaceErrorTemplate,
			$IP;

		// Test mode (not for production!)
		// Set it if not defined
		if ( !isset( $wgDonationInterfaceTestMode) || $wgDonationInterfaceTestMode !== true ) {
			$wgDonationInterfaceTestMode = false;
		}

		/**
		 * Default top-level template file.
		 */
		$wgDonationInterfaceTemplate = __DIR__ . '/gateway_forms/mustache/index.html.mustache';

		/**
		 * Default top-level error template file.
		 */
		$wgDonationInterfaceErrorTemplate = __DIR__ . '/gateway_forms/mustache/error_form.html.mustache';

		// Load the default form settings.
		require_once __DIR__ . '/DonationInterfaceFormSettings.php';

		// Include composer's autoload if the vendor directory exists.  If we have been
		// included via Composer, our dependencies should already be autoloaded at the
		// top level.
		$vendorAutoload = __DIR__ . '/vendor/autoload.php';
		if ( file_exists( $vendorAutoload ) ) {
			require_once ( $vendorAutoload );
		} else {
			require_once ( __DIR__ . '/gateway_common/WmfFramework.php' );
		}
		if ( defined( 'MEDIAWIKI' ) ) {
			// If we're the top-level application, initialize the SmashPig context
			$spConfig = GlobalConfiguration::create();
			Context::init( $spConfig );
			$context = Context::get();
			$context->setSourceName( 'DonationInterface' );
			$context->setSourceType( 'payments' );
			$context->setVersionFromFile( "$IP/.version-stamp");
		}
	}

	public static function onDonationInterfaceUnitTests( &$files ) {
		global $wgAutoloadClasses;

		$testDir = __DIR__ . '/tests/phpunit/';

		// Set up globaltown
		if ( file_exists( $testDir . 'TestConfiguration.php' ) ) {
			require_once $testDir . 'TestConfiguration.php';
		} else {
			return true;
		}

		$files[] = $testDir . 'AllTests.php';

		$wgAutoloadClasses['DonationInterfaceTestCase'] = $testDir . 'DonationInterfaceTestCase.php';
		$wgAutoloadClasses['DonationInterfaceApiTestCase'] = $testDir . 'DonationInterfaceApiTestCase.php';
		$wgAutoloadClasses['MockAmazonClient'] = $testDir . 'includes/MockAmazonClient.php';
		$wgAutoloadClasses['MockAmazonResponse'] = $testDir . 'includes/MockAmazonResponse.php';
		$wgAutoloadClasses['TestingAdyenAdapter'] = $testDir . 'includes/test_gateway/TestingAdyenAdapter.php';
		$wgAutoloadClasses['TestingAmazonAdapter'] = $testDir . 'includes/test_gateway/TestingAmazonAdapter.php';
		$wgAutoloadClasses['TestingAstroPayAdapter'] = $testDir . 'includes/test_gateway/TestingAstroPayAdapter.php';
		$wgAutoloadClasses['TestingDonationLogger'] = $testDir . 'includes/TestingDonationLogger.php';
		$wgAutoloadClasses['TestingGatewayPage'] = $testDir . 'includes/TestingGatewayPage.php';
		$wgAutoloadClasses['TestingGenericAdapter'] = $testDir . 'includes/test_gateway/TestingGenericAdapter.php';
		$wgAutoloadClasses['TestingGlobalCollectAdapter'] = $testDir . 'includes/test_gateway/TestingGlobalCollectAdapter.php';
		$wgAutoloadClasses['TestingGlobalCollectOrphanAdapter'] = $testDir . 'includes/test_gateway/TestingGlobalCollectOrphanAdapter.php';
		$wgAutoloadClasses['TestingPaypalExpressAdapter'] = $testDir . 'includes/test_gateway/TestingPaypalExpressAdapter.php';
		$wgAutoloadClasses['TestingPaypalLegacyAdapter'] = $testDir . 'includes/test_gateway/TestingPaypalLegacyAdapter.php';

		$wgAutoloadClasses['TestingRequest'] = $testDir . 'includes/test_request/test.request.php';

		return true;
	}

	public static function getAdapterClassForGateway( $gateway ) {
		global $wgDonationInterfaceGatewayAdapters;
		if ( !key_exists( $gateway, $wgDonationInterfaceGatewayAdapters ) ) {
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
}
