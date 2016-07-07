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

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group OrphanSlayer
 */
class DonationInterface_Adapter_GlobalCollect_Orphans_GlobalCollectTest extends DonationInterfaceTestCase {
	public function setUp() {
		global $wgGlobalCollectGatewayHtmlFormDir;

		parent::setUp();

		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'cc-vmad' => array(
					'file' => $wgGlobalCollectGatewayHtmlFormDir . '/cc/cc-vmad.html',
					'gateway' => 'globalcollect',
					'payment_methods' => array('cc' => array( 'visa', 'mc', 'amex', 'discover' )),
					'countries' => array(
						'+' => array( 'US', ),
					),
				),
			),
		) );
	}

	/**
	 * @param $name string The name of the test case
	 * @param $data array Any parameters read from a dataProvider
	 * @param $dataName string|int The name or index of the data set
	 */
	function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = 'TestingGlobalCollectOrphanAdapter';
		$this->dummy_utm_data = array (
			'utm_source' => 'dummy_source',
			'utm_campaign' => 'dummy_campaign',
			'utm_medium' => 'dummy_medium',
			'date' => time(),
		);
	}

	public function testConstructor() {

		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject();

		$this->assertInstanceOf( $class, $gateway );

		$this->verifyNoLogErrors();
	}


	public function testBatchOrderID_generate() {

		//no data on construct, generate Order IDs
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => TRUE ) ) );
		$this->assertTrue( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Order_id generation may be broken.' );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is not left as null, when generating our own' );

		$data = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$data['order_id'] = '55555';

		//now, add data and check that we didn't kill the oid. Still generating.
		$gateway->loadDataAndReInit( $data, $useDB = false );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '55555', 'loadDataAndReInit failed to stick OrderID' );

		$data['order_id'] = '444444';
		$gateway->loadDataAndReInit( $data, $useDB = false );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '444444', 'loadDataAndReInit failed to stick OrderID' );

		$this->verifyNoLogErrors();
	}

	public function testBatchOrderID_no_generate() {

		//no data on construct, do not generate Order IDs
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );
		$this->assertFalse( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Deferred order_id generation may be broken.' );
		$this->assertNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is left as null, when not generating our own' );

		$data = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$data['order_id'] = '66666';

		//now, add data and check that we didn't kill the oid. Still not generating
		$gateway->loadDataAndReInit( $data, $useDB = false );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '66666', 'loadDataAndReInit failed to stick OrderID' );

		$data['order_id'] = '777777';
		$gateway->loadDataAndReInit( $data, $useDB = false );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '777777', 'loadDataAndReInit failed to stick OrderID on second batch item' );

		$this->verifyNoLogErrors();
	}

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = array (
			'submethod-mc' => array (
				'nodename' => 'input'
			),
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 1.55, 'USD', $init['language'] . '_' . $init['country'] )
					).
					'\s*$/',
			),
			'state' => array (
				'nodename' => 'select',
				'selected' => 'CA',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}

	/**
	 * Tests to make sure that certain error codes returned from GC will
	 * trigger order cancellation, even if retryable errors also exist.
	 * @dataProvider mcNoRetryCodeProvider
	 */
	public function testNoMastercardFinesForRepeatOnBadCodes( $code ) {
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );

		//Toxic card should not retry, even if there's an order id collision
		$init = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@clean.com';
		$gateway->loadDataAndReInit( $init, $useDB = false );

		$gateway->setDummyGatewayResponseCode( $code );
		$result = $gateway->do_transaction( 'Confirm_CreditCard' );
		$this->assertEquals( 1, count( $gateway->curled ), "Gateway kept trying even with response code $code!  MasterCard could fine us a thousand bucks for that!" );
		$this->assertEquals( false, $result->getCommunicationStatus(), "Error code $code should mean status of do_transaction is false" );
		$errors = $result->getErrors();
		$this->assertFalse( empty( $errors ), 'Orphan adapter needs to see the errors to consider it rectified' );
		$this->assertTrue( array_key_exists( '1000001', $errors ), 'Orphan adapter needs error 1000001 to consider it rectified' );
		$loglines = $this->getLogMatches( LogLevel::INFO, "/Got error code $code, not retrying to avoid MasterCard fines./" );
		$this->assertNotEmpty( $loglines, "GC Error $code is not generating the expected payments log error" );
	}

	/**
	 * Don't fraud-fail someone for bad CVV if GET_ORDERSTATUS
	 * comes back with STATUSID 25 and no CVVRESULT
	 * @group CvvResult
	 */
	function testConfirmCreditCardStatus25() {
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );

		$init = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@clean.com';

		$gateway->loadDataAndReInit( $init, $useDB = false );
		$gateway->setDummyGatewayResponseCode( '25' );

		$gateway->do_transaction( 'Confirm_CreditCard' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( 'process', $action, 'Gateway should not fraud fail on STATUSID 25' );
	}
}
