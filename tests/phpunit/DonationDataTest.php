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
use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\Core\SequenceGenerators;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Splunge
 * @group DonationData
 */
class DonationInterface_DonationDataTest extends DonationInterfaceTestCase {

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$request = RequestContext::getMain()->getRequest();

		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );

		$this->testData = [
			'amount' => '128.00',
			'appeal' => 'JimmyQuote',
			'email' => 'unittest@example.com',
			'first_name' => 'Testocres',
			'last_name' => 'McTestingyou',
			'street_address' => '123 Fake Street',
			'city' => 'Springfield',
			'state_province' => 'US',
			'postal_code' => '99999',
			'country' => 'US',
			'card_num' => '42',
			'expiration' => '1138',
			'cvv' => '665',
			'currency' => 'USD',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'numAttempt' => '5',
			'referrer' => 'http://www.testing.com/',
			'utm_source' => '..cc',
			'utm_medium' => 'large',
			'utm_campaign' => 'yes',
			'wmf_token' => '113811',
			'gateway' => 'DonationData',
			'user_ip' => $request->getIP(),
			'server_ip' => $request->getIP(),
		];
	}

	/**
	 * @covers DonationData::__construct
	 * @covers DonationData::getData
	 * @covers DonationData::populateData
	 */
	public function testConstruct() {
		$context = RequestContext::getMain();
		$request = $context->getRequest();

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ) ); // as if we were posted.
		$returned = $ddObj->getData();
		$expected = [
			'amount' => '0.00',
			'appeal' => 'JimmyQuote',
			'country' => 'XX',
			'currency' => '',
			'email' => '',
			'payment_method' => '',
			'referrer' => '',
			'utm_source' => '..',
			'language' => $context->getLanguage()->getCode(),
			'gateway' => 'globalcollect',
			'payment_submethod' => '',
			'recurring' => '',
			'user_ip' => $request->getIP(),
			'server_ip' => $request->getIP(),
		];
		unset( $returned['contribution_tracking_id'] );
		unset( $returned['order_id'] );
		$this->assertEquals( $expected, $returned, "Staged post data does not match expected (largely empty)." );
	}

	/**
	 * Test construction with external data (for tests and possible batch operations)
	 */
	public function testConstructWithExternalData() {
		$request = RequestContext::getMain()->getRequest();

		$expected = [
			'amount' => '35.00',
			'appeal' => 'JimmyQuote',
			'contribution_tracking_id' => (string)mt_rand(),
			'email' => 'testingdata@wikimedia.org',
			'first_name' => 'Tester',
			'last_name' => 'Testington',
			'street_address' => '548 Market St.',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94104',
			'country' => 'US',
			'card_num' => '378282246310005',
			'expiration' => '0415',
			'cvv' => '001',
			'currency' => 'USD',
			'payment_method' => 'cc',
			'referrer' => 'http://www.baz.test.com/index.php?action=foo&amp;action=bar',
			'utm_source' => 'test_src..cc',
			'utm_medium' => 'test_medium',
			'utm_campaign' => 'test_campaign',
			'language' => 'en',
			'gateway' => 'globalcollect',
			'supplemental_address_1' => '3rd floor',
			'payment_submethod' => 'amex',
			'user_ip' => '12.12.12.12',
			'server_ip' => $request->getIP(),
			'recurring' => '',
		];

		$adapter = $this->getFreshGatewayObject( self::$initial_vars, [ 'batch_mode' => true ] );
		$ddObj = new DonationData( $adapter, $expected ); // external data
		$returned = $ddObj->getData();

		$this->assertNotNull( $returned['contribution_tracking_id'], 'There is no contribution tracking ID' );
		$this->assertNotEquals( $returned['contribution_tracking_id'], '', 'There is not a valid contribution tracking ID' );

		unset( $returned['order_id'] );

		$this->assertEquals( $expected, $returned, "Staged default test data does not match expected." );
	}

	/**
	 * Test construction with data jammed in request.
	 */
	public function testConstructWithFauxRequest() {
		$request = RequestContext::getMain()->getRequest();

		$expected = [
			'amount' => '35.00',
			'appeal' => 'JimmyQuote',
			'email' => 'testingdata@wikimedia.org',
			'first_name' => 'Tester',
			'last_name' => 'Testington',
			'street_address' => '548 Market St.',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94104',
			'country' => 'US',
			'card_num' => '378282246310005',
			'expiration' => '0415',
			'cvv' => '001',
			'currency' => 'USD',
			'payment_method' => 'cc',
			'referrer' => 'http://www.baz.test.com/index.php?action=foo&amp;action=bar',
			'utm_source' => 'test_src..cc',
			'utm_medium' => 'test_medium',
			'utm_campaign' => 'test_campaign',
			'language' => 'en',
			'gateway' => 'globalcollect',
			'supplemental_address_1' => '3rd floor',
			'payment_submethod' => 'amex',
			'user_ip' => $request->getIP(),
			'server_ip' => $request->getIP(),
			'recurring' => '',
		];

		RequestContext::getMain()->setRequest( new FauxRequest( $expected, false ) );

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ) ); // Get all data from request
		$returned = $ddObj->getData();

		$this->assertNotNull( $returned['contribution_tracking_id'], 'There is no contribution tracking ID' );
		$this->assertNotEquals( $returned['contribution_tracking_id'], '', 'There is not a valid contribution tracking ID' );

		unset( $returned['order_id'] );
		unset( $returned['contribution_tracking_id'] );

		$this->assertEquals( $expected, $returned, "Staged default test data does not match expected." );
	}

	/**
	 * Check that constructor outputs certain information to logs
	 */
	public function testDebugLog() {
		$expected = [
			'payment_method' => 'cc',
			'utm_source' => 'test_src..cc',
			'utm_medium' => 'test_medium',
			'utm_campaign' => 'test_campaign',
			'payment_submethod' => 'amex',
			'currency' => 'USD',
		];

		$this->setUpRequest( $expected );

		$ddObj = new DonationData( $this->getFreshGatewayObject() );
		$matches = self::getLogMatches( LogLevel::DEBUG, '/setUtmSource: Payment method is cc, recurring = NULL, utm_source = cc$/' );
		$this->assertNotEmpty( $matches );
		$matches = self::getLogMatches( LogLevel::DEBUG, "/Got currency from 'currency', now: USD$/" );
		$this->assertNotEmpty( $matches );
	}

	/**
	 *
	 */
	public function testRepopulate() {
		$expected = $this->testData;

		// Some changes from the default
		$expected['recurring'] = '';
		$expected['language'] = RequestContext::getMain()->getLanguage()->getCode();
		$expected['gateway'] = 'globalcollect';

		// Just unset a handful... doesn't matter what, really.
		unset( $expected['comment-option'] );
		unset( $expected['email-opt'] );
		unset( $expected['test_string'] );

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $expected ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
		// unset these, because they're always new
		$unsettable = [
			'order_id',
			'contribution_tracking_id'
		];

		foreach ( $unsettable as $thing ) {
			unset( $returned[$thing] );
			unset( $expected[$thing] );
		}

		$this->assertEquals( $expected, $returned, "The forced test data did not populate as expected." );
	}

	/**
	 *
	 */
	public function testIsSomething() {
		$data = $this->testData;
		unset( $data['postal_code'] );

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$this->assertEquals( $ddObj->isSomething( 'postal_code' ), false, "Zip should currently be nothing." );
		$this->assertEquals( $ddObj->isSomething( 'last_name' ), true, "last_name should currently be something." );
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_amtGiven() {
		$data = $this->testData;
		$data['amount'] = 'this is not a number';
		$data['amountGiven'] = 42.50;
		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
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
		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
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
		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
		$this->assertEquals( 3.25, $returned['amount'], "Amount was not properly reset" );
		$this->assertArrayNotHasKey( 'amountOther', $returned, "amountOther should have been removed from the data" );
	}

	/**
	 *
	 */
	public function testSetNormalizedAmount_noGoodAmount() {
		$data = $this->testData;
		$data['amount'] = 'splunge';
		$data['amountGiven'] = 'wombat';
		$data['amountOther'] = 'macedonia';
		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
		$this->assertEquals( 'invalid', $returned['amount'], "Amount was not properly reset" );
		$this->assertArrayNotHasKey( 'amountOther', $returned, "amountOther should have been removed from the data" );
		$this->assertArrayNotHasKey( 'amountGiven', $returned, "amountGiven should have been removed from the data" );
	}

	/**
	 * If the currency code is not three letters, we should try to guess it from
	 * the country code.
	 */
	public function testSetNormalizedCurrencyCode_BadData() {
		$data = $this->testData;
		// When missing or not a recognized currency code, we'll guess from the
		// country - in this test data, US.
		$data['currency'] = 'splunge';
		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data );
		$returned = $ddObj->getData();
		$this->assertEquals( 'USD', $returned['currency'], 'Currency code was not properly reset' );
	}

	/**
	 *
	 */
	public function testSetNormalizedLanguage_uselang() {
		$data = $this->testData;
		unset( $data['uselang'] );
		unset( $data['language'] );

		$data['uselang'] = 'no';

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
		$this->assertEquals( 'no', $returned['language'], "Language 'no' was normalized out of existance. Sad." );
		$this->assertArrayNotHasKey( 'uselang', $returned, "'uselang' should have been removed from the data" );
	}

	/**
	 *
	 */
	public function testSetNormalizedLanguage_language() {
		$data = $this->testData;
		unset( $data['uselang'] );
		unset( $data['language'] );

		$data['language'] = 'no';

		$ddObj = new DonationData( $this->getFreshGatewayObject( self::$initial_vars ), $data ); // change to test mode with explicit test data
		$returned = $ddObj->getData();
		$this->assertEquals( 'no', $returned['language'], "Language 'no' was normalized out of existance. Sad." );
		$this->assertArrayNotHasKey( 'uselang', $returned, "'uselang' should have been removed from the data" );
	}

	/**
	 *
	*/
	public function testSendToContributionTrackingQueue() {
		$queueName = 'contribution-tracking';
		$generator = SequenceGenerators\Factory::getSequenceGenerator( $queueName );
		$generator->initializeSequence();
		$expected = [
			'referrer' => 'http://www.testing.com/',
			'utm_source' => '..cc',
			'utm_medium' => 'large',
			'utm_campaign' => 'yes',
			'language' => 'en',
			'country' => 'US',
			'form_amount' => 'USD 128.00',
			'payments_form' => 'globalcollect.JimmyQuote',
			'id' => '1',
			];

		$gateway = $this->getFreshGatewayObject( $this->testData );
		$ctId = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		$actual = QueueWrapper::getQueue( $queueName )->pop();
		SourceFields::removeFromMessage( $actual );
		unset( $actual['ts'] );

		$this->assertEquals( $expected, $actual, 'Message on the queue does not match' );
		$this->assertEquals( $expected[ 'id' ], $ctId, 'Wrong contribution tracking ID set' );

		$empty = QueueWrapper::getQueue( $queueName )->pop();
		$this->assertNull( $empty, 'Too many messages on the queue' );
	}

	public function testGetSessionFields() {
		$actual = DonationData::getSessionFields();
		$expected = [
			'contribution_tracking_id',
			'anonymous',
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'language',
			'email',
			'first_name',
			'last_name',
			'street_address',
			'supplemental_address_1',
			'city',
			'state_province',
			'country',
			'postal_code',
			'gateway',
			'gateway_account',
			'gateway_txn_id',
			'order_id',
			'subscr_id',
			'recurring',
			'payment_method',
			'payment_submethod',
			'response',
			'currency',
			'amount',
			'user_ip',
			'date',
			'gateway_session_id',
			'recurring_payment_token',
			'opt_in',
			'employer',
			'employer_id',
			'order_id',
			'appeal',
			'variant',
			'processor_form',
			'referrer',
			'contact_id',
			'contact_hash',
			'processor_contact_id'
		];
		$this->assertArrayEquals( $expected, $actual, false );
	}

	/**
	 * TODO: Make sure ALL these functions in DonationData are tested, either directly or through a calling function.
	 * I know that's more regression-ish, but I stand by it. :p
	public function setNormalizedOrderIDs(){
	public function generateOrderId() {
	public function sanitizeInput( &$value, $key, $flags=ENT_COMPAT, $double_encode=false ) {
	public function setGateway(){
	public function doCacheStuff(){
	public function getEditToken( $salt = '' ) {
	public static function generateToken( $salt = '' ) {
	public function matchEditToken( $val, $salt = '' ) {
	public function unsetEditToken() {
	public function checkTokens() {
	public function wasPosted(){
	public function setUtmSource() {
	public function getCleanTrackingData( $unset = false ) {
	public function saveContributionTracking() {
	public static function insertContributionTracking( $tracking_data ) {
	public function updateContributionTracking( $force = false ) {

	*/
}
