<?php

/**
 * Donation Interface
 *
 *  To install the DonationInterface extension, put the following line in LocalSettings.php:
 *	require_once( "\$IP/extensions/DonationInterface/DonationInterface.php" );
 *
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'DonationInterface' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	// Load the interface messages that are shared across multiple gateways
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/gateway_common/i18n/interface';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/gateway_common/i18n/country-specific';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/gateway_common/i18n/countries';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/gateway_common/i18n/us-states';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/gateway_common/i18n/canada-provinces';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/amazon_gateway/i18n';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/globalcollect_gateway/i18n';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/adyen_gateway/i18n';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/astropay_gateway/i18n';
	$wgMessagesDirs['DonationInterface'][] = __DIR__ . '/paypal_gateway/i18n';
	$wgExtensionMessagesFiles['GatewayAliases'] = __DIR__ . '/DonationInterface.alias.php';
	$wgExtensionMessagesFiles['AmazonGatewayAlias'] = __DIR__ . '/amazon_gateway/amazon_gateway.alias.php';
	$wgExtensionMessagesFiles['GlobalCollectGatewayAlias'] = __DIR__ . '/globalcollect_gateway/globalcollect_gateway.alias.php';
	$wgExtensionMessagesFiles['AdyenGatewayAlias'] = __DIR__ . '/adyen_gateway/adyen_gateway.alias.php';
	$wgExtensionMessagesFiles['AstropayGatewayAlias'] = __DIR__ . '/astropay_gateway/astropay_gateway.alias.php';
	$wgExtensionMessagesFiles['PaypalGatewayAlias'] = __DIR__ . '/paypal_gateway/paypal_gateway.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for DonationInterface extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the DonationInterface extension requires MediaWiki 1.27+' );
}
