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
 */

//@TODO: This, in all the other AllTests files, or better yet: Somewhere central.
//Deeply tired of this. This crap should clearly be automatic for all the test
//files, when they exist... or we'll think we're running tests that are, in
//reality, being ignored. So angry.
if ( $handle = opendir( dirname( __FILE__ ) ) ) {
	while ( ( $file = readdir( $handle ) ) !== false ) {
		if ( strpos( $file, 'TestCase.php' ) ) {
			require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $file;
		}
	}
}

/**
 * AllTests
 */
class DonationInterface_Adapter_GlobalCollect_AllTests
{

	/**
	 * Run the main test and load any parameters if needed.
	 *
	 */
	public static function main()
	{
		$parameters = array();

		PHPUnit_TextUI_TestRunner::run( self::suite(), $parameters );
	}

	/**
	 * Regular suite
	 *
	 * All tests except those that require output buffering.
	 *
	 * @return PHPUnit_Framework_TestSuite
	 */
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite( 'Donation Interface - Adapter Suite' );

		// General adapter tests
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_GlobalCollectTestCase' );
		// Orphan Slayer tests
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_Orphans_GlobalCollectTestCase' );

		// Bank transfer tests
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_BankTransferTestCase' );

		//Direct Debit tests
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_DirectDebitTestCase' );

		// Real time bank transfer tests
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferEnetsTestCase' );
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferEpsTestCase' );
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferIdealTestCase' );
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferNordeaSwedenTestCase' );
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_RealTimeBankTransferSofortuberweisungTestCase' );
		$suite->addTestSuite( 'DonationInterface_Adapter_GlobalCollect_YandexTestCase' );

		// Form load test cases
		$suite->addTestSuite( 'GlobalCollectFormLoadTestCase' );

		return $suite;
	}
}
