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
			'wgDonationInterfacePriceCeiling' => 100.00,
		) );

		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';

		$this->page = new TestingGatewayPage();
		$this->adapter = new TestingGenericAdapter();
		$this->page->adapter = $this->adapter;
		parent::setUp();
	}

	public function testPassesValidation() {
		$this->adapter->addRequestData( array(
			'amount' => '2.00',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'foo@localhost.net',
		) );

		$this->page->validateForm();

		$this->assertTrue( $this->adapter->validatedOK() );
	}

	public function testLowAmountError() {
		$this->adapter->addRequestData( array(
			'amount' => '1.99',
			'country' => 'US',
			'currency' => 'USD',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'amount', $errors );
	}

	public function testHighAmountError() {
		$this->adapter->addRequestData( array(
			'amount' => '100.99',
			'country' => 'US',
			'currency' => 'USD',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'amount', $errors );
	}

	public function testCurrencyCodeError() {
		$this->adapter->addRequestData( array(
			'amount' => '2.99',
			'country' => 'US',
			'currency' => 'ZZZ',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'currency_code', $errors );
	}

	public function testCountryError() {
		// TODO: also validate and test country=ZZ and XX

		$this->setMwGlobals( array(
			'wgDonationInterfaceForbiddenCountries' => array( 'XX' )
		) );

		$this->adapter->addRequestData( array(
			'amount' => '2.99',
			'country' => 'XX',
			'currency' => 'USD',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'country', $errors );
	}

	public function testEmailError() {
		$this->adapter->addRequestData( array(
			'amount' => '2.99',
			'currency' => 'USD',
			'email' => 'foo',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'emailAdd', $errors );
	}

	public function testSpuriousCcError() {
		$this->adapter->addRequestData( array(
			'amount' => '2.99',
			'currency' => 'USD',
			'fname' => '4111111111111111',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'fname', $errors );
	}

	public function testMissingFieldError() {
		$this->adapter->addRequestData( array(
			'amount' => '2.99',
		) );

		$this->page->validateForm();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getValidationErrors();
		$this->assertArrayHasKey( 'currency_code', $errors );
	}
}
