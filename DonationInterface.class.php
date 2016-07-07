<?php

class DonationInterface {
	/**
	 * Executed after processing extension.json
	 */
	public static function registerExtension() {
		global $wgDonationInterfaceTestMode, $wgDonationInterfaceFormDirs,
			$wgDonationInterfaceHtmlFormDir, $wgGlobalCollectGatewayHtmlFormDir,
			$wgWorldpayGatewayHtmlFormDir, $wgDonationInterfaceTemplate,
			$wgDonationInterfaceErrorTemplate;

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

		$wgDonationInterfaceHtmlFormDir = __DIR__ . '/gateway_forms/rapidhtml/html';

		$wgGlobalCollectGatewayHtmlFormDir = __DIR__ . '/globalcollect_gateway/forms/html';

		$wgWorldpayGatewayHtmlFormDir = __DIR__ . '/worldpay_gateway/forms/html';

		/**
		 * Base directories for each gateway's form templates.
		 */
		$wgDonationInterfaceFormDirs = array(
			'default' => $wgDonationInterfaceHtmlFormDir,
			'gc' => $wgGlobalCollectGatewayHtmlFormDir,
			'worldpay' => $wgWorldpayGatewayHtmlFormDir,
		);

		// Load the default form settings.
		require_once __DIR__ . '/DonationInterfaceFormSettings.php';

		// Include composer's autoload if the vendor directory exists.  If we have been
		// included via Composer, our dependencies should already be autoloaded at the
		// top level.
		// Note that in WMF's continuous integration, we can still only use stuff from
		// Composer if it is already in Mediawiki's vendor directory, such as monolog
		$vendorAutoload = __DIR__ . '/vendor/autoload.php';
		if ( file_exists( $vendorAutoload ) ) {
			require_once ( $vendorAutoload );
		} else {
			require_once ( __DIR__ . '/gateway_common/WmfFramework.php' );
		}
	}

	public static function onDonationInterfaceUnitTests( &$files ) {
		global $wgAutoloadClasses;

		$testDir = __DIR__ . '/tests/';

		$files[] = $testDir . 'AllTests.php';

		$wgAutoloadClasses['DonationInterfaceTestCase'] = $testDir . 'DonationInterfaceTestCase.php';
		$wgAutoloadClasses['MockAmazonClient'] = $testDir . 'includes/MockAmazonClient.php';
		$wgAutoloadClasses['MockAmazonResponse'] = $testDir . 'includes/MockAmazonResponse.php';
		$wgAutoloadClasses['TestingQueue'] = $testDir . 'includes/TestingQueue.php';
		$wgAutoloadClasses['TestingAdyenAdapter'] = $testDir . 'includes/test_gateway/TestingAdyenAdapter.php';
		$wgAutoloadClasses['TestingAmazonAdapter'] = $testDir . 'includes/test_gateway/TestingAmazonAdapter.php';
		$wgAutoloadClasses['TestingAmazonGateway'] = $testDir . 'includes/test_page/TestingAmazonGateway.php';
		$wgAutoloadClasses['TestingAstroPayAdapter'] = $testDir . 'includes/test_gateway/TestingAstroPayAdapter.php';
		$wgAutoloadClasses['TestingDonationLogger'] = $testDir . 'includes/TestingDonationLogger.php';
		$wgAutoloadClasses['TestingGatewayPage'] = $testDir . 'includes/TestingGatewayPage.php';
		$wgAutoloadClasses['TestingGenericAdapter'] = $testDir . 'includes/test_gateway/TestingGenericAdapter.php';
		$wgAutoloadClasses['TestingGlobalCollectAdapter'] = $testDir . 'includes/test_gateway/TestingGlobalCollectAdapter.php';
		$wgAutoloadClasses['TestingGlobalCollectGateway'] = $testDir . 'includes/test_page/TestingGlobalCollectGateway.php';
		$wgAutoloadClasses['TestingGlobalCollectOrphanAdapter'] = $testDir . 'includes/test_gateway/TestingGlobalCollectOrphanAdapter.php';
		$wgAutoloadClasses['TestingPaypalLegacyAdapter'] = $testDir . 'includes/test_gateway/TestingPaypalLegacyAdapter.php';
		$wgAutoloadClasses['TestingWorldpayAdapter'] = $testDir . 'includes/test_gateway/TestingWorldpayAdapter.php';
		$wgAutoloadClasses['TestingWorldpayGateway'] = $testDir . 'includes/test_page/TestingWorldpayGateway.php';

		$wgAutoloadClasses['TestingRequest'] = $testDir . 'includes/test_request/test.request.php';

		return true;
	}
}
