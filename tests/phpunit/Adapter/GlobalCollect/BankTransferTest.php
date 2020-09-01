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

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group BankTransfer
 */
class DonationInterface_Adapter_GlobalCollect_BankTransferTest extends DonationInterfaceTestCase {
	public function setUp(): void {
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
			'form_name' => 'TwoStepAmount',
			'payment_method' => 'bt',
			'payment_submethod' => 'bt',
			'payment_product_id' => 11,
		];

		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}
}
