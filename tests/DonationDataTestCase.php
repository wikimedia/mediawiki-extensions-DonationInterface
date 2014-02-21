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
 * @author Katie Horn <khorn@wikimedia.org>
 */

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * @group Fundraising
 * @group Splunge
 * @group Gateways
 * @author Katie Horn <khorn@wikimedia.org>
 */
class DonationInterface_DonationDataTestCase extends DonationInterfaceTestCase {

	/**
	 *
	 */
	public function __construct(){
		global $wgRequest;

		parent::__construct();

		$this->testData = array(
			'amount' => '128.00',
			'email' => 'unittest@example.com',
			'fname' => 'Testocres',
			'mname' => 'S.',
			'lname' => 'McTestingyou',
			'street' => '123 Fake Street',
			'city' => 'Springfield',
			'state' => 'US',
			'zip' => '99999',
			'country' => 'US',
			'fname2' => 'Testor',
			'lname2' => 'Testeyton',
			'street2' => '123 W. Grand Ave.',
			'city2' => 'Oakland',
			'state2' => 'CA',
			'zip2' => '94607',
			'country2' => 'US',
			'size' => 'Big mcLargeHuge',
			'premium_language' => 'fr',
			'card_num' => '42',
			'card_type' => 'visa',
			'expiration' => '1138',
			'cvv' => '665',
			'currency_code' => 'USD',
			'payment_method' => '',
			'numAttempt' => '5',
			'referrer' => 'http://www.testing.com/',
			'utm_source' => '..cc',
			'utm_medium' => 'large',
			'utm_campaign' => 'yes',
			'comment-option' => '',
			'comment' => 'My hovercraft is full of eels',
			'email-opt' => '',
			'test_string' => '',
			'token' => '113811',
			'contribution_tracking_id' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => 'DonationData',
			'owa_session' => '',
			'owa_ref' => 'http://localhost/importedTestData',
			'user_ip' => $wgRequest->getIP(),
			'server_ip' => $wgRequest->getIP(),
		);

	}


	/**
	 * @covers DonationData::__construct
	 * @covers DonationData::getDataEscaped
	 * @covers DonationData::populateData
	 * @covers DonationData::doCacheStuff
	 */
	public function testConstruct(){
		global $wgLanguageCode, $wgRequest;

		$ddObj = new DonationData(''); //as if we were posted.
		$returned = $ddObj->getDataEscaped();
		$expected = array(  'posted' => '',
			'amount' => '0.00',
			'email' => 'nobody@wikimedia.org',
			'fname' => '',
			'mname' => '',
			'lname' => '',
			'street' => '',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => '',
			'fname2' => '',
			'lname2' => '',
			'street2' => '',
			'city2' => '',
			'state2' => '',
			'zip2' => '',
			'country2' => '',
			'size' => '',
			'premium_language' => $wgLanguageCode,
			'card_num' => '',
			'card_type' => '',
			'expiration' => '',
			'cvv' => '',
			'currency_code' => '',
			'payment_method' => '',
			'numAttempt' => '0',
			'referrer' => '',
			'utm_source' => '..cc',
			'utm_medium' => '',
			'utm_campaign' => '',
			'language' => $wgLanguageCode,
			'comment' => '',
			'_cache_' => '',
			'token' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => 'DonationData',
			'owa_session' => '',
			'owa_ref' => '',
			'street_supplemental' => '',
			'currency' => '',
			'payment_submethod' => '',
			'issuer_id' => '',
			'utm_source_id' => '',
			'utm_key' => '',
			'descriptor' => '',
			'account_name' => '',
			'account_number' => '',
			'authorization_id' => '',
			'bank_check_digit' => '',
			'bank_name' => '',
			'bank_code' => '',
			'branch_code' => '',
			'country_code_bank' => '',
			'date_collect' => '',
			'direct_debit_text' => '',
			'iban' => '',
			'fiscal_number' => '',
			'transaction_type' => '',
			'form_name' => '',
			'ffname' => '',
			'recurring' => '',
			'recurring_paypal' => '',
			'user_ip' => $wgRequest->getIP(),
			'server_ip' => $wgRequest->getIP(),
			'form_class' => '',
		);
		unset($returned['contribution_tracking_id']);
		unset($returned['order_id']);
		$this->assertEquals($expected, $returned, "Staged post data does not match expected (largely empty).");
	}

	/**
	 *
	 */
	public function testConstructAsTest(){
		global $wgRequest;

		$ddObj = new DonationData('', true); //test mode from the start, no data
		$returned = $ddObj->getDataEscaped();
		$expected = array(
			'amount' => '35.00',
			'email' => 'test@example.com',
			'fname' => 'Tester',
			'mname' => 'T.',
			'lname' => 'Testington',
			'street' => '548 Market St.',
			'city' => 'San Francisco',
			'state' => 'CA',
			'zip' => '94104',
			'country' => 'US',
			'fname2' => 'Testy',
			'lname2' => 'Testerson',
			'street2' => '123 Telegraph Ave.',
			'city2' => 'Berkeley',
			'state2' => 'CA',
			'zip2' => '94703',
			'country2' => 'US',
			'size' => 'small',
			'premium_language' => 'es',
			'card_num' => '378282246310005',
			'card_type' => 'american',
			'expiration' => '0714',
			'cvv' => '001',
			'currency_code' => 'USD',
			'payment_method' => 'cc',
			'numAttempt' => '0',
			'referrer' => 'http://www.baz.test.com/index.php?action=foo&amp;action=bar',
			'utm_source' => 'test_src..cc',
			'utm_medium' => 'test_medium',
			'utm_campaign' => 'test_campaign',
			'language' => 'en',
			'comment' => '0',
			'token' => '',
			'data_hash' => '',
			'action' => '',
			'gateway' => 'DonationData',
			'owa_session' => '',
			'owa_ref' => 'http://localhost/defaultTestData',
			'street_supplemental' => '3rd floor',
			'payment_submethod' => 'american',
			'issuer_id' => '',
			'utm_source_id' => '',
			'user_ip' => '12.12.12.12',
			'server_ip' => $wgRequest->getIP(),
			'recurring' => '',
			'form_class' => '',
		);

		$this->assertNotNull( $returned['contribution_tracking_id'], 'There is no contribution tracking ID' );
		$this->assertNotEquals( $returned['contribution_tracking_id'], '', 'There is not a valid contribution tracking ID' );

		unset($returned['order_id']);
		unset($returned['contribution_tracking_id']);

		$this->assertEquals($expected, $returned, "Staged default test data does not match expected.");
	}

	/**
	 *
	 */
	public function testRepopulate(){
		global $wgLanguageCode;

		$expected = $this->testData;

		// Some changes from the default
		$expected['recurring'] = '';
		$expected['language'] = $wgLanguageCode;
		$expected['form_class'] = '';

		// Just unset a handful... doesn't matter what, really.
		unset($expected['comment-option']);
		unset($expected['email-opt']);
		unset($expected['test_string']);

		$ddObj = new DonationData( '', true, $expected ); //change to test mode with explicit test data
		$returned = $ddObj->getDataEscaped();
		//unset these, because they're always new
		$unsettable = array(
			'order_id',
			'contribution_tracking_id'
		);

		foreach ( $unsettable as $thing ) {
			unset( $returned[$thing] );
			unset( $expected[$thing] );
		}

		$this->assertEquals( $expected, $returned, "The forced test data did not populate as expected." );
	}

	/**
	 *
	 */
	public function testIsSomething(){
		$data = $this->testData;
		unset($data['zip']);
		$ddObj = new DonationData('', true, $data);
		$this->assertEquals($ddObj->isSomething('zip'), false, "Zip should currently be nothing.");
		$this->assertEquals($ddObj->isSomething('lname'), true, "Lname should currently be something.");
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_amtGiven() {
		$data = $this->testData;
		$data['amount'] = 'this is not a number';
		$data['amountGiven'] = 42.50;
		//unset($data['zip']);
		$ddObj = new DonationData('', true, $data);
		$returned = $ddObj->getDataEscaped();
		$this->assertEquals( 42.50, $returned['amount'], "Amount was not properly reset" );
		$this->assertArrayNotHasKey( 'amountGiven', $returned, "amountGiven should have been removed from the data" );
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_amount() {
		$data = $this->testData;
		$data['amount'] = 88.15;
		$data['amountGiven'] = 42.50;
		//unset($data['zip']);
		$ddObj = new DonationData('', true, $data);
		$returned = $ddObj->getDataEscaped();
		$this->assertEquals( 88.15, $returned['amount'], "Amount was not properly reset" );
		$this->assertArrayNotHasKey( 'amountGiven', $returned, "amountGiven should have been removed from the data" );
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_negativeAmount() {
		$data = $this->testData;
		$data['amount'] = -1;
		$data['amountOther'] = 3.25;
		//unset($data['zip']);
		$ddObj = new DonationData('', true, $data);
		$returned = $ddObj->getDataEscaped();
		$this->assertEquals(3.25, $returned['amount'], "Amount was not properly reset");
		$this->assertArrayNotHasKey( 'amountOther', $returned, "amountOther should have been removed from the data");
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_noGoodAmount() {
		$data = $this->testData;
		$data['amount'] = 'splunge';
		$data['amountGiven'] = 'wombat';
		$data['amountOther'] = 'macedonia';
		//unset($data['zip']);
		$ddObj = new DonationData('', true, $data);
		$returned = $ddObj->getDataEscaped();
		$this->assertEquals( 'invalid', $returned['amount'], "Amount was not properly reset");
		$this->assertArrayNotHasKey( 'amountOther', $returned, "amountOther should have been removed from the data");
		$this->assertArrayNotHasKey( 'amountGiven', $returned, "amountGiven should have been removed from the data");
	}

	/**
	 * TODO: Make sure ALL these functions in DonationData are tested, either directly or through a calling function.
	 * I know that's more regression-ish, but I stand by it. :p
	function setNormalizedOrderIDs(){
	function generateOrderId() {
	public function sanitizeInput( &$value, $key, $flags=ENT_COMPAT, $double_encode=false ) {
	function setGateway(){
	function doCacheStuff(){
	public function getEditToken( $salt = '' ) {
	public static function generateToken( $salt = '' ) {
	function matchEditToken( $val, $salt = '' ) {
	function unsetEditToken() {
	public function checkTokens() {
	function wasPosted(){
	function setUtmSource() {
	public function getOptOuts() {
	public function getCleanTrackingData( $clean_optouts = false ) {
	function saveContributionTracking() {
	public static function insertContributionTracking( $tracking_data ) {
	public function updateContributionTracking( $force = false ) {

	*/
}


