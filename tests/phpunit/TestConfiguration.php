<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This file contains custom options and constants for test configuration.
 */

use SmashPig\PaymentData\ValidationAction;

/**
 * TESTS_MESSAGE_NOT_IMPLEMENTED
 *
 * Message for code that has not been implemented.
 */
define( 'TESTS_MESSAGE_NOT_IMPLEMENTED', 'Not implemented yet!' );

/**
 * TESTS_HOSTNAME
 *
 * The hostname for the system
 */
define( 'TESTS_HOSTNAME', 'localhost' );

/**
 * TESTS_EMAIL
 *
 * An email address to use in case test send mail
 */
define( 'TESTS_EMAIL', 'nobody@wikimedia.org' );

/**
 * TESTS_GATEWAY_DEFAULT
 *
 * This is the default gateway that will be used to implement unit tests.
 */
define( 'TESTS_GATEWAY_DEFAULT', 'IngenicoGateway' );

/**
 * TESTS_ADAPTER_DEFAULT
 *
 * This is the default adapter that will be used to implement unit tests.
 */
define( 'TESTS_ADAPTER_DEFAULT', IngenicoAdapter::class );

global $wgDonationInterfaceTest,
	$wgDonationInterfaceMerchantID,
	$wgDonationInterfaceGatewayAdapters,
	$wgDonationInterfaceThankYouPage,
	$wgDonationInterface3DSRules,
	$wgPaypalExpressGatewayURL,
	$wgPaypalExpressGatewayTestingURL,
	$wgPaypalExpressGatewaySignatureURL,
	$wgPaypalExpressGatewayAccountInfo,
	$wgAmazonGatewayReturnURL,
	$wgAmazonGatewayAccountInfo,
	$wgAmazonGatewayFallbackCurrency,
	$wgAmazonGatewayNotifyOnConvert,
	$wgAdyenCheckoutGatewayURL,
	$wgAdyenCheckoutGatewayAccountInfo,
	$wgGravyGatewayAccountInfo,
	$wgDlocalGatewayAccountInfo,
	$wgDlocalGatewayFallbackCurrency,
	$wgDonationInterfaceMinFraudAccountId,
	$wgDonationInterfaceMinFraudLicenseKey,
	$wgDonationInterfaceMinFraudClientOptions,
	$wgDonationInterfaceEnableMinFraud,
	$wgDonationInterfaceEnableFunctionsFilter,
	$wgDonationInterfaceEnableReferrerFilter,
	$wgDonationInterfaceEnableSourceFilter,
	$wgDonationInterfaceCustomFiltersActionRanges,
	$wgDonationInterfaceCustomFiltersRefRules,
	$wgDonationInterfaceCustomFiltersSrcRules,
	$wgDonationInterfaceCustomFiltersFunctions,
	$wgDonationInterfaceCustomFiltersInitialFunctions,
	$wgIngenicoGatewayCustomFiltersFunctions,
	$wgDonationInterfaceCountryMap,
	$wgDonationInterfaceUtmCampaignMap,
	$wgDonationInterfaceUtmSourceMap,
	$wgDonationInterfaceUtmMediumMap,
	$wgDonationInterfaceEmailDomainMap,
	$wgMainCacheType;

$wgMainCacheType = 'hash';

$wgDonationInterfaceGatewayAdapters = [
	'ingenico' => IngenicoAdapter::class,
	'amazon' => AmazonAdapter::class,
	'adyen' => AdyenCheckoutAdapter::class,
	'dlocal' => TestingDlocalAdapter::class,
	'paypal_ec' => TestingPaypalExpressAdapter::class,
	'braintree' => BraintreeAdapter::class,
	'gravy' => GravyAdapter::class,
];
/**
 * Make sure the test setup is used, else we'll have the wrong classes.
 */
/** DonationInterface General Settings */
$wgDonationInterfaceTest = true;
$wgDonationInterfaceMerchantID = 'test';

$wgDonationInterfaceThankYouPage = 'https://donate.wikimedia.org/wiki/Thank_You';

/** Paypal Express Checkout */
$wgPaypalExpressGatewayURL = 'https://api-3t.sandbox.paypal.com/nvp';
$wgPaypalExpressGatewayTestingURL = 'https://api-3t.sandbox.paypal.com/nvp';
$wgPaypalExpressGatewaySignatureURL = $wgPaypalExpressGatewayURL;
$wgPaypalExpressGatewayAccountInfo['test'] = [
	'User' => 'phpunittesting@wikimedia.org',
	'Password' => '9876543210',
	'Signature' => 'ABCDEFGHIJKLMNOPQRSTUV-ZXCVBNMLKJHGFDSAPOIUYTREWQ',
	'RedirectURL' => 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=',
];

/** Amazon */
$wgAmazonGatewayReturnURL = 'https://payments.wikimedia.org/index.php/Special:AmazonGateway';
$wgAmazonGatewayAccountInfo = [];
$wgAmazonGatewayAccountInfo['test'] = [
	'SellerID' => 'ABCDEFGHIJKL',
	'ClientID' => 'amzn1.application-oa2-client.1a2b3c4d5e',
	'WidgetScriptURL' =>
		'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
	'ReturnURL' => "https://example.org/index.php/Special:AmazonGateway?debug=true",
];
$wgAmazonGatewayFallbackCurrency = false;
$wgAmazonGatewayNotifyOnConvert = false;

/** Adyen Checkout */
$wgAdyenCheckoutGatewayURL = 'https://testorwhatevercheckout.adyen.com';
$wgAdyenCheckoutGatewayAccountInfo = [];
$wgAdyenCheckoutGatewayAccountInfo['testMerchantAccountName'] = [
	'Script' => [
		'src' => 'test-pear.js',
		'integrity' => 'test-hash'
	],
	'Css' => [
		'src' => 'test-apple.css',
		'integrity' => 'test-hash',
	],
	'ClientKey' => 'test',
	'Environment' => 'test',
	'GoogleMerchantId' => '1234',
	'GoogleScript' => 'test-google.js',
];

/** Dlocal */
$wgDlocalGatewayAccountInfo = [];
$wgDlocalGatewayAccountInfo['test'] = [
	'enableINDirectBT' => false, // if we want to use india direct bank transfer, true
	'dlocalScript' => 'https://js-sandbox.dlocal.com/', // For production, use "https://js.dlocal.com/"
	'smartFieldApiKey' => 'xxx-yyy-zzz-aaa-bbb'
];
$wgDlocalGatewayFallbackCurrency = false;

$wgDonationInterfaceMinFraudAccountId = 1;
$wgDonationInterfaceMinFraudLicenseKey = 'testkey';

$wgDonationInterfaceMinFraudClientOptions = [
	'host' => 'minfraud.wikimedia.org',
];

/** Gravy */
$wgGravyGatewayID = 'TestID';
$wgGravyId = 'Random-ID';
$wgGravyEnvironment = 'test';
$wgGravyMerchantAccountID = 'default';
$wgGravyGatewayAccountInfo['WikimediaDonations'] = [
	'gravyID' => $wgGravyGatewayID,
	'environment' => $wgGravyEnvironment,
	'merchantAccountID' => 'test',
	'secureFieldsJS' => "https://cdn.$wgGravyGatewayID.gravy.app/secure-fields/latest/secure-fields.js",
	'secureFieldsCSS' => "https://cdn.$wgGravyGatewayID.gravy.app/secure-fields/latest/secure-fields.css",
	'GoogleScript' => "https://pay.google.com/gp/p/js/pay.js",
	'gravyGooglePayMerchantId' => "app.gr4vy.{$wgGravyEnvironment}.{$wgGravyGatewayID}.{$wgGravyMerchantAccountID}",
	'GoogleMerchantId' => 'test-google-id',
	'googleEnvironment' => 'test',
	'AppleScript' => 'test-apple.js',
];

// still can't quite handle minFraud by itself yet, so default like this.
// I will turn it on for individual tests in which I want to verify that it at
// least fails closed when enabled.
$wgDonationInterfaceEnableMinFraud = false;

// ...but we want these.
$wgDonationInterfaceEnableFunctionsFilter = true;
$wgDonationInterfaceEnableReferrerFilter = true;
$wgDonationInterfaceEnableSourceFilter = true;

$customFilters = [
	'getScoreCountryMap' => 50,
	'getScoreUtmCampaignMap' => 50,
	'getScoreUtmSourceMap' => 15,
	'getScoreUtmMediumMap' => 15,
	'getScoreEmailDomainMap' => 75,
];

$wgDonationInterfaceCustomFiltersActionRanges = [
	ValidationAction::PROCESS => [ 0, 25 ],
	ValidationAction::REVIEW => [ 25, 50 ],
	ValidationAction::CHALLENGE => [ 50, 75 ],
	ValidationAction::REJECT => [ 75, 100 ],
];

$wgDonationInterfaceCustomFiltersRefRules = [
	'/donate-error/i' => 5,
];

$wgDonationInterfaceCustomFiltersSrcRules = [ '/wikimedia\.org/i' => 80 ];

$wgDonationInterfaceCustomFiltersFunctions = $customFilters;

$wgIngenicoGatewayCustomFiltersFunctions = [
	'getCVVResult' => 20,
	'getAVSResult' => 25,
] + $customFilters;

$wgDonationInterfaceCustomFiltersInitialFunctions = [];

$wgDonationInterfaceCountryMap = [
	'US' => 40,
	'CA' => 15,
	'RU' => -4,
];

$wgDonationInterfaceUtmCampaignMap = [
	'/^(C14_)/' => 14,
	'/^(spontaneous)/' => 5
];

$wgDonationInterfaceUtmSourceMap = [
	'/somethingmedia/' => 70
];

$wgDonationInterfaceUtmMediumMap = [
	'/somethingmedia/' => 80
];

$wgDonationInterfaceEmailDomainMap = [
	'wikimedia.org' => 42,
	'wikipedia.org' => 50,
];

$wgDonationInterface3DSRules = [ 'INR' => 'IN' ];
