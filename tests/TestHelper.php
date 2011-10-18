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
 *
 * @category	UnitTesting
 * @package		Fundraising_QueueHandling
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GENERAL PUBLIC LICENSE
 * @since		r462
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
 * Set error reporting to the level to which code must comply.
 */
error_reporting( E_ALL | E_STRICT );


/**
 * @see DonationData
 */
require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'gateway_common/DonationData.php';

/**
 * @see GatewayAdapter
 */
require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'gateway_common/gateway.adapter.php';

/**
 * @see GlobalCollectAdapter
 */
require_once dirname( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'globalcollect_gateway/globalcollect.adapter.php';

/**
 * @see ContributionTrackingProcessor
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'ContributionTracking/ContributionTracking.processor.php';

/**
 * TESTS_WEB_ROOT
 *
 * This is similar to $IP, the installation path in Mediawiki.
 */
define( 'TESTS_WEB_ROOT', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) );

/*
 * Unit tests are run from the command line.
 *
 * It should be confirmed that this will not affect other tests such as Selenium.
 */
$wgCommandLineMode = true;

/*
 * Load the user-defined test configuration file, if it exists; otherwise, load
 * the default configuration.
 */
if ( is_file( 'TestConfiguration.php' ) ) {
   require_once 'TestConfiguration.php';
} else {
	require_once 'TestConfiguration.php.dist';
}

