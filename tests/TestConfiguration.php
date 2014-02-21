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
 * @since		r98249
 * @author		Jeremy Postlethwaite <jpostlethwaite@wikimedia.org>
 */

/*
 * Include PHPUnit dependencies
 */
require_once 'PHPUnit/Framework/IncompleteTestError.php';
require_once 'PHPUnit/Framework/TestCase.php';
require_once 'PHPUnit/Framework/TestSuite.php';
require_once 'PHPUnit/Runner/Version.php';
require_once 'PHPUnit/TextUI/TestRunner.php';
require_once 'PHPUnit/Util/Filter.php';

/*
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

/**
 * TESTS_PFP_CREDIT_CARDS_AMEREICAN_EXPRESS_VALID_CARD
 *
 * A "valid" test American Express Card for PayFlowPro.
 */
define( 'TESTS_PFP_CREDIT_CARDS_AMEREICAN_EXPRESS_VALID_CARD', '378282246310005' );

/**
 * Make sure the test setup is used, else we'll have the wrong classes.
 */
/** DonationInterface General Settings **/
$wgDonationInterfaceTestMode = true;
$wgDonationInterfaceMerchantID = 'test';

$wgDonationInterfaceAllowedHtmlForms = array(
	'test' => array(
	),
);

$wgDonationInterfaceThankYouPage = wfExpandUrl( '/Donate-thanks' );


/** GlobalCollect **/
$wgGlobalCollectGatewayAccountInfo = array ( );
$wgGlobalCollectGatewayAccountInfo['test'] = array (
	'MerchantID' => 'test',
);


/** Paypal **/
$wgPaypalGatewayAccountInfo = array ( );
$wgPaypalGatewayAccountInfo['testing'] = array (
	'AccountEmail' => 'phpunittesting@wikimedia.org',
);
$wgPaypalGatewayReturnURL = 'http://donate.wikimedia.org'; //whatever, doesn't matter.


/** Amazon **/
$wgAmazonGatewayReturnURL = 'https://payments.wikimedia.org/index.php/Special:AmazonGateway';
$wgAmazonGatewayAccountInfo['test'] = array (
	'AccessKey' => 'testkey',
	'SecretKey' => 'testsecret',
	'PaymentsAccountID' => 'testaccountid',
	'IpnOverride' => 'https://test.wikimedia.org/amazon',
);

/** Adyen * */
$wgAdyenGatewayBaseURL = 'https://testorwhatever.adyen.com';
$wgAdyenGatewayAccountInfo['test'] = array (
	'AccountName' => 'wikitest',
	'SharedSecret' => 'long-cat-is-long',
	'SkinCode' => 'testskin',
);