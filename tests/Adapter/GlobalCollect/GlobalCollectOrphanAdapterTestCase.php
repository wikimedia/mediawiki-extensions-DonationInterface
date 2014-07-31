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

/**
 * @see DonationInterfaceTestCase
 */
require_once dirname( dirname( dirname( __FILE__ ) ) ) . DIRECTORY_SEPARATOR . 'DonationInterfaceTestCase.php';

/**
 * 
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group OrphanSlayer
 */
class DonationInterface_Adapter_GlobalCollect_Orphans_GlobalCollectTestCase extends DonationInterfaceTestCase {

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

		$this->verifyNoLogErrors( $gateway );
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

		$this->verifyNoLogErrors( $gateway );
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

		$this->verifyNoLogErrors( $gateway );
	}

	public function testGCFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmad';

		$assertNodes = array (
			'cc-mc' => array (
				'nodename' => 'input'
			),
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '$1.55',
			),
			'state' => array (
				'nodename' => 'select',
				'selected' => 'CA',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}

	function testGCFormLoad_FR() {
		$init = $this->getDonorTestData( 'FR' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['ffname'] = 'cc-vmaj';

		$assertNodes = array (
			'selected-amount' => array (
				'nodename' => 'span',
				'innerhtml' => '€1.55',
			),
			'fname' => array (
				'nodename' => 'input',
				'value' => 'Prénom',
			),
			'lname' => array (
				'nodename' => 'input',
				'value' => 'Nom',
			),
			'informationsharing' => array (
				'nodename' => 'p',
				'innerhtml' => 'En faisant ce don, vous acceptez notre politique de confidentialité en matière de donation ainsi que de partager vos données personnelles avec la Fondation Wikipedia et ses prestataires de services situés aux Etats-Unis et ailleurs, dont les lois sur la protection de la vie privée ne sont pas forcement équivalentes aux vôtres.',
			),
			'country' => array (
				'nodename' => 'select',
				'selected' => 'FR',
			),
		);

		$this->verifyFormOutput( 'TestingGlobalCollectGateway', $init, $assertNodes, true );
	}
}
