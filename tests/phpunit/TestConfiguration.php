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
define( 'TESTS_GATEWAY_DEFAULT', 'GlobalCollectGateway' );

/**
 * TESTS_ADAPTER_DEFAULT
 *
 * This is the default adapter that will be used to implement unit tests.
 */
define( 'TESTS_ADAPTER_DEFAULT', 'TestingGlobalCollectAdapter' );

global $wgDonationInterfaceTest,
	$wgDonationInterfaceMerchantID,
	$wgDonationInterfaceGatewayAdapters,
	$wgDonationInterfaceAllowedHtmlForms,
	$wgDonationInterfaceThankYouPage,
	$wgGlobalCollectGatewayAccountInfo,
	$wgPaypalGatewayAccountInfo,
	$wgPaypalGatewayReturnURL,
	$wgPaypalExpressGatewayURL,
	$wgPaypalExpressGatewayTestingURL,
	$wgPaypalExpressGatewaySignatureURL,
	$wgPaypalExpressGatewayAccountInfo,
	$wgAmazonGatewayReturnURL,
	$wgAmazonGatewayAccountInfo,
	$wgAdyenGatewayURL,
	$wgAdyenGatewayAccountInfo,
	$wgAstroPayGatewayURL,
	$wgAstroPayGatewayTestingURL,
	$wgAstroPayGatewayAccountInfo,
	$wgAstroPayGatewayFallbackCurrency,
	$wgDonationInterfaceMinFraudUserId,
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
	$wgGlobalCollectGatewayCustomFiltersFunctions,
	$wgIngenicoGatewayCustomFiltersFunctions,
	$wgDonationInterfaceCountryMap,
	$wgDonationInterfaceUtmCampaignMap,
	$wgDonationInterfaceUtmSourceMap,
	$wgDonationInterfaceUtmMediumMap,
	$wgDonationInterfaceEmailDomainMap,
	$wgMainCacheType;

$wgMainCacheType = 'hash';

$wgDonationInterfaceGatewayAdapters = array(
	'globalcollect' => 'TestingGlobalCollectAdapter',
	'ingenico' => 'IngenicoAdapter',
	'amazon' => 'TestingAmazonAdapter',
	'adyen' => 'TestingAdyenAdapter',
	'astropay' => 'TestingAstroPayAdapter',
	'paypal_ec' => 'TestingPaypalExpressAdapter',
	'paypal' => 'TestingPaypalLegacyAdapter'
);
/**
 * Make sure the test setup is used, else we'll have the wrong classes.
 */
/** DonationInterface General Settings **/
$wgDonationInterfaceTest = true;
$wgDonationInterfaceMerchantID = 'test';

$wgDonationInterfaceThankYouPage = 'https://wikimediafoundation.org/wiki/Thank_You';

/** GlobalCollect **/
$wgGlobalCollectGatewayAccountInfo = array();
$wgGlobalCollectGatewayAccountInfo['test'] = array(
	'MerchantID' => 'test',
);

/** Paypal **/
$wgPaypalGatewayAccountInfo = array();
$wgPaypalGatewayAccountInfo['testing'] = array(
	'AccountEmail' => 'phpunittesting@wikimedia.org',
);
$wgPaypalGatewayReturnURL = 'http://donate.wikimedia.org'; // whatever, doesn't matter.

/** Paypal Express Checkout **/
$wgPaypalExpressGatewayURL = 'https://api-3t.sandbox.paypal.com/nvp';
$wgPaypalExpressGatewayTestingURL = 'https://api-3t.sandbox.paypal.com/nvp';
$wgPaypalExpressGatewaySignatureURL = $wgPaypalExpressGatewayURL;
$wgPaypalExpressGatewayAccountInfo['test'] = array(
	'User' => 'phpunittesting@wikimedia.org',
	'Password' => '9876543210',
	'Signature' => 'ABCDEFGHIJKLMNOPQRSTUV-ZXCVBNMLKJHGFDSAPOIUYTREWQ',
	'RedirectURL' => 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=',
);

/** Amazon **/
$wgAmazonGatewayReturnURL = 'https://payments.wikimedia.org/index.php/Special:AmazonGateway';
$wgAmazonGatewayAccountInfo = array();
$wgAmazonGatewayAccountInfo['test'] = array(
	'SellerID' => 'ABCDEFGHIJKL',
	'ClientID' => 'amzn1.application-oa2-client.1a2b3c4d5e',
	'ClientSecret' => '12432g134e3421a41234b1341c324123d',
	'MWSAccessKey' => 'N0NSENSEXYZ',
	'MWSSecretKey' => 'iuasd/2jhaslk2j49lkaALksdJLsJLas+',
	'Region' => 'us',
	'WidgetScriptURL' =>
		'https://static-na.payments-amazon.com/OffAmazonPayments/us/sandbox/js/Widgets.js',
	'ReturnURL' => "https://example.org/index.php/Special:AmazonGateway?debug=true",
);

/** Adyen **/
$wgAdyenGatewayURL = 'https://testorwhatever.adyen.com';
$wgAdyenGatewayAccountInfo = array();
$wgAdyenGatewayAccountInfo['test'] = array(
	'AccountName' => 'wikitest',
	'Skins' => [
		'testskin' => [
			'SharedSecret' => 'C7F1D9E29479CF18131063A742CD2703FB9D48BAB0160693045E3FB7B8508E59',
			'Name' => 'base',
		],
		'altskin' => [
			'SharedSecret' => 'A78B329F29872E21291063A742CD2703FB9D48BAB01606930421291063A742CD',
			'Name' => 'redirect',
		]
	],
);

/** AstroPay **/
$wgAstroPayGatewayURL = 'https://astropay.example.com/';
$wgAstroPayGatewayTestingURL = 'https://sandbox.astropay.example.com/';
$wgAstroPayGatewayAccountInfo = array();
$wgAstroPayGatewayAccountInfo['test'] = array(
	'Create' => array(
		'Login' => 'createlogin',
		'Password' => 'createpass',
	),
	'Status' => array(
		'Login' => 'statuslogin',
		'Password' => 'statuspass',
	),
	'SecretKey' => 'NanananananananananananananananaBatman',
);
$wgAstroPayGatewayFallbackCurrency = false;

$wgDonationInterfaceMinFraudUserId = 1;
$wgDonationInterfaceMinFraudLicenseKey = 'testkey';

$wgDonationInterfaceMinFraudClientOptions = array(
	'host' => 'minfraud.wikimedia.org',
);

// still can't quite handle minFraud by itself yet, so default like this.
// I will turn it on for individual tests in which I want to verify that it at
// least fails closed when enabled.
$wgDonationInterfaceEnableMinFraud = false;

// ...but we want these.
$wgDonationInterfaceEnableFunctionsFilter = true;
$wgDonationInterfaceEnableReferrerFilter = true;
$wgDonationInterfaceEnableSourceFilter = true;

$customFilters = array(
	'getScoreCountryMap' => 50,
	'getScoreUtmCampaignMap' => 50,
	'getScoreUtmSourceMap' => 15,
	'getScoreUtmMediumMap' => 15,
	'getScoreEmailDomainMap' => 75,
);

$wgDonationInterfaceCustomFiltersActionRanges = array(
	'process' => array( 0, 25 ),
	'review' => array( 25, 50 ),
	'challenge' => array( 50, 75 ),
	'reject' => array( 75, 100 ),
);

$wgDonationInterfaceCustomFiltersRefRules = array(
	'/donate-error/i' => 5,
);

$wgDonationInterfaceCustomFiltersSrcRules = array( '/wikimedia\.org/i' => 80 );

$wgDonationInterfaceCustomFiltersFunctions = $customFilters;

$wgGlobalCollectGatewayCustomFiltersFunctions = array(
	'getCVVResult' => 20,
	'getAVSResult' => 25,
) + $customFilters;

$wgIngenicoGatewayCustomFiltersFunctions = $wgGlobalCollectGatewayCustomFiltersFunctions;

$wgDonationInterfaceCountryMap = array(
	'US' => 40,
	'CA' => 15,
	'RU' => -4,
);

$wgDonationInterfaceUtmCampaignMap = array(
	'/^(C14_)/' => 14,
	'/^(spontaneous)/' => 5
);

$wgDonationInterfaceUtmSourceMap = array(
	'/somethingmedia/' => 70
);

$wgDonationInterfaceUtmMediumMap = array(
	'/somethingmedia/' => 80
);

$wgDonationInterfaceEmailDomainMap = array(
	'wikimedia.org' => 42,
	'wikipedia.org' => 50,
);
