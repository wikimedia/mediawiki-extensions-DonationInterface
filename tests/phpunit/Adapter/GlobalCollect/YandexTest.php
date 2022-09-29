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
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group Yandex
 */
class DonationInterface_Adapter_GlobalCollect_YandexTest extends DonationInterfaceTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgGlobalCollectGatewayEnabled' => true,
		] );
	}

	/**
	 * testBuildRequestXml
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXml() {
		$optionsForTestData = [
			'payment_method' => 'ew',
			'payment_submethod' => 'ew_yandex',
			'payment_product_id' => 849,
			'descriptor' => 'Wikimedia Foundation/Wikipedia', // all ewallets have this
		];

		// somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );
		unset( $options['descriptor'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}

	public function testFormAction() {
		$optionsForTestData = [
			'payment_method' => 'ew',
			'payment_submethod' => 'ew_yandex',
		];

		// somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );

		$this->gatewayAdapter = $this->getFreshGatewayObject( $options );
		$this->gatewayAdapter->do_transaction( "INSERT_ORDERWITHPAYMENT" );
		$action = $this->gatewayAdapter->getTransactionDataFormAction();
		$this->assertEquals( "url_placeholder", $action, "The formaction was not populated as expected (yandex)." );
	}

}
