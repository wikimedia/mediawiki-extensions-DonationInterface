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
			// FIXME: base class sketchiness.
			'wgDonationInterfaceGatewayAdapters' => array(
				'donation' => 'TestingGatewayAdapter',
			),
			'wgDonationInterfacePriceFloor' => 2.00,
			'wgDonationInterfacePriceCeiling' => 100.00,
		) );

		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';

		$this->page = new TestingGatewayPage();
	}

	public function setUpAdapter( $data = array() ) {
		$this->adapter = new TestingGenericAdapter( array(
			'external_data' => $data,
		) );
		$this->page->adapter = $this->adapter;
	}

	public function tearDown() {
		TestingGenericAdapter::$fakeIdentifier = null;
		TestingGenericAdapter::$acceptedCurrencies = array();
		parent::tearDown();
	}

	public function testPassesValidation() {
		$this->setUpAdapter( array(
			'amount' => '2.00',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'foo@localhost.net',
		) );

		$this->assertTrue( $this->adapter->validatedOK() );
	}

	public function testLowAmountError() {
		$this->setUpAdapter( array(
			'amount' => '1.99',
			'country' => 'US',
			'currency' => 'USD',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'amount', $errors );
	}

	public function testHighAmountError() {
		$this->setUpAdapter( array(
			'amount' => '100.99',
			'country' => 'US',
			'currency' => 'USD',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'amount', $errors );
	}

	public function testCurrencyCodeError() {
		$this->setUpAdapter( array(
			'amount' => '2.99',
			'country' => 'BR',
			'currency' => 'BRL',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'currency_code', $errors );
	}

	public function testCountryError() {
		// TODO: also validate and test country=ZZ and XX

		$this->setMwGlobals( array(
			'wgDonationInterfaceForbiddenCountries' => array( 'XX' )
		) );

		$this->setUpAdapter( array(
			'amount' => '2.99',
			'country' => 'XX',
			'currency' => 'USD',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'country', $errors );
	}

	public function testEmailError() {
		$this->setUpAdapter( array(
			'amount' => '2.99',
			'currency' => 'USD',
			'email' => 'foo',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'email', $errors );
	}

	public function testSpuriousCcError() {
		$this->setUpAdapter( array(
			'amount' => '2.99',
			'currency' => 'USD',
			'fname' => '4111111111111111',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'fname', $errors );
	}

	public function testMissingFieldError() {
		$this->setUpAdapter( array(
			'amount' => '2.99',
		) );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'currency_code', $errors );
	}

	/**
	 * @covers DataValidator::validate_gateway
	 */
	public function testBadGatewayError() {
		$badGateway = uniqid();

		// The gateway is calculated from adapter class, so this validation
		// doesn't happen in practice and we can't fake a bad gateway using
		// GatewayAdapter::addRequestData().

		TestingGenericAdapter::$fakeIdentifier = $badGateway;
		$this->setUpAdapter();

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrors();
		$this->assertArrayHasKey( 'general', $errors );
	}
}
