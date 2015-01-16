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
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayPage
 */
class GatewayValidationTest extends DonationInterfaceTestCase {

	protected $page;
	protected $adapter;

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgDonationInterfaceEnabledGateways' => array( 'donation' ), // base class.  awkward.
			'wgDonationInterfacePriceFloor' => 2.00,
		) );

		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';

		$this->page = new TestingGatewayPage();
		$this->adapter = new TestingGenericAdapter();
		$this->page->adapter = $this->adapter;
		parent::setUp();
	}

	public function testPassesValidation() {
		$this->adapter->addData( array(
			'amount' => '2.00',
			'country' => 'US',
			'currency_code' => 'USD',
		) );

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );
	}

	public function testLowAmountError() {
		$this->adapter->addData( array(
			'amount' => '1.99',
			'country' => 'US',
			'currency_code' => 'USD',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'amount', $errors );
	}
}
