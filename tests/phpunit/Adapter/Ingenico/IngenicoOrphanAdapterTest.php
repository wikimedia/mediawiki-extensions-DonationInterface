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
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 * @group OrphanSlayer
 */
class DonationInterface_Adapter_Ingenico_Orphans_IngenicoTest extends DonationInterfaceTestCase {
	public function setUp() {
		parent::setUp();
		$this->markTestSkipped( 'Orphan adapter not yet implemented' );

		TestingContext::get()->providerConfigurationOverride =
			TestingProviderConfiguration::createForProvider(
				'ingenico',
				$this->smashPigGlobalConfig
			);

		$this->setMwGlobals( array(
			'wgIngenicoGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => array(
				'cc-vmad' => array(
					'gateway' => 'ingenico',
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
		$this->dummy_utm_data = array (
			'utm_source' => 'dummy_source',
			'utm_campaign' => 'dummy_campaign',
			'utm_medium' => 'dummy_medium',
			'date' => time(),
		);
	}

	public function testConstructor() {

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
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '55555', 'loadDataAndReInit failed to stick OrderID' );

		$data['order_id'] = '444444';
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '444444', 'loadDataAndReInit failed to stick OrderID' );

		$this->verifyNoLogErrors();
	}

	public function testBatchOrderID_no_generate() {

		//no data on construct, do not generate Order IDs
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );
		$this->assertFalse( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Deferred order_id generation may be broken.' );
		$this->assertEmpty( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is left as null, when not generating our own' );

		$data = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$data['order_id'] = '66666';

		//now, add data and check that we didn't kill the oid. Still not generating
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '66666', 'loadDataAndReInit failed to stick OrderID' );

		$data['order_id'] = '777777';
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '777777', 'loadDataAndReInit failed to stick OrderID on second batch item' );

		$this->verifyNoLogErrors();
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
		$init['contribution_tracking_id'] = mt_rand();
		$gateway->loadDataAndReInit( $init );

		$gateway->setDummyGatewayResponseCode( $code );
		$result = $gateway->do_transaction( 'Confirm_CreditCard' );
		$this->assertEquals( 1, count( $gateway->curled ), "Gateway kept trying even with response code $code!  MasterCard could fine us a thousand bucks for that!" );
		$this->assertEquals( false, $result->getCommunicationStatus(), "Error code $code should mean status of do_transaction is false" );
		$errors = $result->getErrors();
		$this->assertFalse( empty( $errors ), 'Orphan adapter needs to see the errors to consider it rectified' );
		$finder = function( $error ) {
			return $error->getErrorCode() == '1000001';
		};
		$this->assertNotEmpty( array_filter( $errors, $finder ), 'Orphan adapter needs error 1000001 to consider it rectified' );
		$loglines = $this->getLogMatches( LogLevel::INFO, "/Got error code $code, not retrying to avoid MasterCard fines./" );
		$this->assertNotEmpty( $loglines, "GC Error $code is not generating the expected payments log error" );
	}

	/**
	 * Make sure we're incorporating GET_ORDERSTATUS AVS and CVV responses into
	 * fraud scores.
	 */
	function testGetOrderstatusPostProcessFraud() {
		$this->markTestSkipped( 'OrderStatus not yet implemented' );
		$this->setMwGlobals( array(
			'wgDonationInterfaceEnableCustomFilters' => true,
			'wgIngenicoGatewayCustomFiltersFunctions' => array(
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			),
		) );
		$gateway = $this->getFreshGatewayObject( null, array ( 'order_id_meta' => array ( 'generate' => FALSE ) ) );

		$init = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@manichean.com';
		$init['contribution_tracking_id'] = mt_rand();
		$init['payment_method'] = 'cc';

		$gateway->loadDataAndReInit( $init );
		$gateway->setDummyGatewayResponseCode( '600_badCvv' );

		$gateway->do_transaction( 'Confirm_CreditCard' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( 'review', $action,
			'Orphan gateway should fraud fail on bad CVV and AVS' );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 40, $exposed->risk_score,
			'Risk score was incremented correctly.' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = array(
			'validation_action' => 'review',
			'risk_score' => 40,
			'score_breakdown' => array(
				// FIXME: need to enable utm / email / country checks ???
				'initial' => 0,
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			),
			'user_ip' => null, // FIXME
			'gateway_txn_id' => '55555',
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'ingenico',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		);
		$this->assertEquals( $expected, $message );
	}
}
