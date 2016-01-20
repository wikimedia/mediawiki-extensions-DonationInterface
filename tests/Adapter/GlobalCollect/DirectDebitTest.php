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
 * @group RealTimeBankTransfer
 */
class DonationInterface_Adapter_GlobalCollect_DirectDebitTest extends DonationInterfaceTestCase {
	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnabled' => true,
		) );
	}

	/**
	 * testBuildRequestXml
	 *
	 * @covers GatewayAdapter::__construct
	 * @covers GatewayAdapter::setCurrentTransaction
	 * @covers GatewayAdapter::buildRequestXML
	 * @covers GatewayAdapter::getData_Unstaged_Escaped
	 */
	public function testBuildRequestXmlForDirectDebitSpain() {

		$optionsForTestData = array(
			'form_name' => 'RapidHTML',
			'payment_method' => 'dd',
			'payment_submethod' => 'dd_es',
			'payment_product_id' => 709,
		);

		//somewhere else?
		$options = $this->getDonorTestData( 'ES' );
		$options = array_merge( $options, $optionsForTestData );
		unset( $options['payment_product_id'] );
		unset( $options['payment_submethod'] );

		$dd_info_supplied = array (
			'branch_code' => '123',
			'account_name' => 'Henry',
			'account_number' => '21',
			'bank_code' => '37',
			'bank_check_digit' => 'BD',
			'direct_debit_text' => 'testy test test',
		);
		$dd_info_expected = array (
			'branch_code' => '0123', //4, apparently.
			'account_name' => 'Henry',
			'account_number' => '0000000021', //10
			'bank_code' => '0037', //4
			'bank_check_digit' => 'BD',
			'direct_debit_text' => 'Wikimedia Foundation', //hard-coded in the gateway
		);
		$optionsForTestData = array_merge( $optionsForTestData, $dd_info_expected );
		$options = array_merge( $options, $dd_info_supplied );

		$this->buildRequestXmlForGlobalCollect( $optionsForTestData, $options );
	}
}

