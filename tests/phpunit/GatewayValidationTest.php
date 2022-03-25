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

use SmashPig\Core\ValidationError;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayPage
 */
class GatewayValidationTest extends DonationInterfaceTestCase {

	protected $page;
	protected $adapter;

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			// FIXME: base class sketchiness.
			'wgDonationInterfaceGatewayAdapters' => [
				'donation' => TestingGatewayAdapter::class,
			]
		] );

		TestingGenericAdapter::$acceptedCurrencies[] = 'USD';
		TestingGenericAdapter::$donationRules = [
			'min' => 2.00,
			'max' => 100.00
		];

		$this->page = new TestingGatewayPage();
	}

	protected function setUpAdapter( $data = [] ) {
		$this->adapter = new TestingGenericAdapter( [
			'external_data' => $data,
		] );
		$this->page->adapter = $this->adapter;
	}

	public function tearDown(): void {
		TestingGenericAdapter::$fakeIdentifier = null;
		TestingGenericAdapter::$acceptedCurrencies = [];
		parent::tearDown();
	}

	public function assertHasValidationError( $field, $messageKey = null, $messageParams = null ) {
		$hasError = false;
		foreach ( $this->adapter->getErrorState()->getErrors() as $error ) {
			if ( $error instanceof ValidationError && $error->getField() === $field ) {
				$hasError = true;
				if ( $messageKey !== null ) {
					$this->assertEquals( $messageKey, $error->getMessageKey() );
				}
				if ( $messageParams !== null ) {
					$this->assertEquals( $messageParams, $error->getMessageParams() );
				}
			}
		}
		$this->assertTrue( $hasError );
	}

	public function testPassesValidation() {
		$this->setUpAdapter( [
			'amount' => '2.00',
			'country' => 'US',
			'currency' => 'USD',
			'email' => 'foo@localhost.net',
		] );

		$this->assertTrue( $this->adapter->validatedOK() );
	}

	public function testLowAmountError() {
		$this->setUpAdapter( [
			'amount' => '1.99',
			'country' => 'US',
			'currency' => 'USD',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrorState();
		$this->assertTrue( $errors->hasValidationError( 'amount' ) );
	}

	public function testHighAmountError() {
		$this->setUpAdapter( [
			'amount' => '100.99',
			'country' => 'US',
			'currency' => 'USD',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );

		$errors = $this->adapter->getErrorState();
		$this->assertTrue( $errors->hasValidationError( 'amount' ) );
	}

	public function testCurrencyCodeError() {
		$this->setUpAdapter( [
			'amount' => '2.99',
			'country' => 'BR',
			'currency' => 'BRL',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );

		$this->assertHasValidationError( 'currency' );
	}

	public function testCountryError() {
		// TODO: also validate and test country=ZZ and XX

		$this->setMwGlobals( [
			'wgDonationInterfaceForbiddenCountries' => [ 'US' ]
		] );

		$this->setUpAdapter( [
			'amount' => '2.99',
			'country' => 'US',
			'currency' => 'USD',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );

		$this->assertHasValidationError( 'country' );
	}

	public function testEmailError() {
		$this->setUpAdapter( [
			'amount' => '2.99',
			'currency' => 'USD',
			'email' => 'foo',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );

		$this->assertHasValidationError( 'email' );
	}

	public function testSpuriousCcError() {
		$this->setUpAdapter( [
			'amount' => '2.99',
			'currency' => 'USD',
			'first_name' => '4111111111111111',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );
		$this->assertHasValidationError( 'first_name' );
	}

	public function testMissingFieldError() {
		$this->setUpAdapter( [
			'amount' => '2.99',
		] );

		$this->assertFalse( $this->adapter->validatedOK() );
		$this->assertHasValidationError( 'currency' );
	}
}
