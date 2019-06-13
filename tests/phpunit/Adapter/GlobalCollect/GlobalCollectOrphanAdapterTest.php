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
use SmashPig\CrmLink\ValidationAction;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;
use Wikimedia\TestingAccessWrapper;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group OrphanSlayer
 */
class DonationInterface_Adapter_GlobalCollect_Orphans_GlobalCollectTest extends DonationInterfaceTestCase {
	public function setUp() {
		parent::setUp();

		TestingContext::get()->providerConfigurationOverride =
			TestingProviderConfiguration::createForProvider(
				'globalcollect',
				$this->smashPigGlobalConfig
			);

		$this->setMwGlobals( [
			'wgGlobalCollectGatewayEnabled' => true,
			'wgDonationInterfaceAllowedHtmlForms' => [
				'cc-vmad' => [
					'gateway' => 'globalcollect',
					'payment_methods' => [ 'cc' => [ 'visa', 'mc', 'amex', 'discover' ] ],
					'countries' => [
						'+' => [ 'US', ],
					],
				],
			],
		] );
	}

	/**
	 * @param string $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->testAdapterClass = TestingGlobalCollectOrphanAdapter::class;
		$this->dummy_utm_data = [
			'utm_source' => 'dummy_source',
			'utm_campaign' => 'dummy_campaign',
			'utm_medium' => 'dummy_medium',
			'date' => time(),
		];
	}

	public function testConstructor() {
		$options = $this->getDonorTestData();
		$class = $this->testAdapterClass;

		$gateway = $this->getFreshGatewayObject();

		$this->assertInstanceOf( $class, $gateway );

		$this->verifyNoLogErrors();
	}

	public function testBatchOrderID_generate() {
		// no data on construct, generate Order IDs
		$gateway = $this->getFreshGatewayObject( null, [ 'order_id_meta' => [ 'generate' => true ] ] );
		$this->assertTrue( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Order_id generation may be broken.' );
		$this->assertNotNull( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is not left as null, when generating our own' );

		$data = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$data['order_id'] = '55555';

		// now, add data and check that we didn't kill the oid. Still generating.
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '55555', 'loadDataAndReInit failed to stick OrderID' );

		$data['order_id'] = '444444';
		$gateway->loadDataAndReInit( $data );
		$this->assertEquals( $gateway->getData_Unstaged_Escaped( 'order_id' ), '444444', 'loadDataAndReInit failed to stick OrderID' );

		$this->verifyNoLogErrors();
	}

	public function testBatchOrderID_no_generate() {
		// no data on construct, do not generate Order IDs
		$gateway = $this->getFreshGatewayObject( null, [ 'order_id_meta' => [ 'generate' => false ] ] );
		$this->assertFalse( $gateway->getOrderIDMeta( 'generate' ), 'The order_id meta generate setting override is not working properly. Deferred order_id generation may be broken.' );
		$this->assertEmpty( $gateway->getData_Unstaged_Escaped( 'order_id' ), 'Failed asserting that an absent order id is left as null, when not generating our own' );

		$data = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$data['order_id'] = '66666';

		// now, add data and check that we didn't kill the oid. Still not generating
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
		$gateway = $this->getFreshGatewayObject( null, [ 'order_id_meta' => [ 'generate' => false ] ] );

		// Toxic card should not retry, even if there's an order id collision
		$init = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@clean.com';
		$init['contribution_tracking_id'] = mt_rand();
		$gateway->loadDataAndReInit( $init );

		$gateway::setDummyGatewayResponseCode( $code );
		$result = $gateway->do_transaction( 'Confirm_CreditCard' );
		$this->assertEquals( 1, count( $gateway->curled ), "Gateway kept trying even with response code $code!  Mastercard could fine us a thousand bucks for that!" );
		$this->assertEquals( false, $result->getCommunicationStatus(), "Error code $code should mean status of do_transaction is false" );
		$errors = $result->getErrors();
		$this->assertFalse( empty( $errors ), 'Orphan adapter needs to see the errors to consider it rectified' );
		$finder = function ( $error ) {
			return $error->getErrorCode() == '1000001';
		};
		$this->assertNotEmpty( array_filter( $errors, $finder ), 'Orphan adapter needs error 1000001 to consider it rectified' );
		$loglines = self::getLogMatches( LogLevel::INFO, "/Got error code $code, not retrying to avoid Mastercard fines./" );
		$this->assertNotEmpty( $loglines, "GC Error $code is not generating the expected payments log error" );
	}

	/**
	 * Make sure we're incorporating GET_ORDERSTATUS AVS and CVV responses into
	 * fraud scores.
	 */
	function testGetOrderstatusPostProcessFraud() {
		$this->setMwGlobals( [
			'wgDonationInterfaceEnableCustomFilters' => true,
			'wgGlobalCollectGatewayCustomFiltersFunctions' => [
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			],
		] );
		$gateway = $this->getFreshGatewayObject( null, [ 'order_id_meta' => [ 'generate' => false ] ] );

		$init = array_merge( $this->getDonorTestData(), $this->dummy_utm_data );
		$init['ffname'] = 'cc-vmad';
		$init['order_id'] = '55555';
		$init['email'] = 'innocent@manichean.com';
		$init['contribution_tracking_id'] = mt_rand();
		$init['payment_method'] = 'cc';

		$gateway->loadDataAndReInit( $init );
		$gateway::setDummyGatewayResponseCode( '600_badCvv' );

		$gateway->do_transaction( 'Confirm_CreditCard' );
		$action = $gateway->getValidationAction();
		$this->assertEquals( ValidationAction::REVIEW, $action,
			'Orphan gateway should fraud fail on bad CVV and AVS' );

		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 40, $exposed->risk_score,
			'Risk score was incremented correctly.' );

		$initialMessage = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		$validateMessage = QueueWrapper::getQueue( 'payments-antifraud' )->pop();

		SourceFields::removeFromMessage( $initialMessage );
		SourceFields::removeFromMessage( $validateMessage );

		$expectedInitial = [
			'validation_action' => ValidationAction::PROCESS,
			'risk_score' => 0,
			'score_breakdown' => [
				// FIXME: need to enable utm / email / country checks ???
				'initial' => 0,
			],
			'user_ip' => null, // FIXME
			'gateway_txn_id' => false,
			'date' => $initialMessage['date'],
			'server' => gethostname(),
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];

		$expectedValidate = [
			'validation_action' => ValidationAction::REVIEW,
			'risk_score' => 40,
			'score_breakdown' => [
				// FIXME: need to enable utm / email / country checks ???
				'initial' => 0,
				'getCVVResult' => 10,
				'getAVSResult' => 30,
			],
			'user_ip' => null, // FIXME
			'gateway_txn_id' => '55555',
			'date' => $validateMessage['date'],
			'server' => gethostname(),
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];

		$this->assertEquals( $expectedInitial, $initialMessage );
		$this->assertEquals( $expectedValidate, $validateMessage );
	}
}
