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
 */

/**
 * @group	Fundraising
 * @group	DonationInterface
 * @group	Validation
 */
class AmountTest  extends DonationInterfaceTestCase {

	/**
	 * @var GatewayType
	 */
	protected $adapter;
	/**
	 * @var Amount
	 */
	protected $validator;
	/**
	 * @var array
	 */
	protected $errors;
	/**
	 * @var array
	 */
	protected $normalized;

	public function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgDonationInterfacePriceFloor' => 1.50,
			'wgDonationInterfacePriceCeiling' => 100,
			'wgLanguageCode' => 'en',
		) );

		$this->setUpRequest( array(
			'country' => 'US',
			'uselang' => 'en',
		) );

		$this->normalized = array(
			'language' => 'en',
			'country' => 'US',
			'currency_code' => 'USD',
		);

		$this->errors = array();
		$this->adapter = new TestingGenericAdapter();
		$this->validator = new Amount();
	}

	protected function validate() {
		$this->validator->validate(
			$this->adapter, $this->normalized, $this->errors
		);
	}

	public function testValidUsd() {
		$this->normalized['amount'] = '10.00';
		$this->validate();
		$this->assertEmpty( $this->errors, 'Error shown for valid amount' );
	}

	public function testZeroAmount() {
		$this->normalized['amount'] = '0.00';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for zero amount' );
		$expected = DataValidator::getErrorMessage(
			'amount', 'not_empty', 'en'
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for zero amount'
		);
	}

	public function testWhitespaceAmount() {
		$this->normalized['amount'] = '    ';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for whitespace amount' );
		$expected = DataValidator::getErrorMessage(
			'amount', 'not_empty', 'en'
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for whitespace amount'
		);
	}

	public function testNonNumericAmount() {
		$this->normalized['amount'] = 'XYZ123';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for non-numeric amount' );
		$this->assertEquals(
			WmfFramework::formatMessage( 'donate_interface-error-msg-invalid-amount' ),
			$this->errors['amount'],
			'Wrong error message for non-numeric amount'
		);
	}

	public function testNegativeAmount() {
		$this->normalized['amount'] = '-100.00';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for negative amount' );
		$this->assertEquals(
			WmfFramework::formatMessage( 'donate_interface-error-msg-invalid-amount' ),
			$this->errors['amount'],
			'Wrong error message for negative amount'
		);
	}

	public function testTooMuchUsd() {
		$this->normalized['amount'] = '101.00';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for excessive amount (USD)' );
		$expected = WmfFramework::formatMessage(
			'donate_interface-bigamount-error',
			100,
			'USD',
			$this->adapter->getGlobal( 'MajorGiftsEmail' )
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for excessive amount (USD)'
		);
	}

	public function testTooLittleUsd() {
		$this->normalized['amount'] = '1.49';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for diminutive amount (USD)' );

		$formattedMin = Amount::format( 1.50, 'USD', 'en_US' );
		$expected = WmfFramework::formatMessage(
			'donate_interface-smallamount-error',
			$formattedMin
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for diminutive amount (USD)'
		);
	}

	// Conversion tests depend on Barbadian monetary policy
	// BBD is convenient as it's pegged to $0.50
	public function testTooMuchBbd() {
		$this->normalized['currency_code'] = 'BBD';
		$this->normalized['amount'] = '201.00';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for excessive amount (BBD)' );
		$expected = WmfFramework::formatMessage(
			'donate_interface-bigamount-error',
			200,
			'BBD',
			$this->adapter->getGlobal( 'MajorGiftsEmail' )
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for excessive amount (BBD)'
		);
	}

	public function testTooLittleBbd() {
		$this->normalized['currency_code'] = 'BBD';
		$this->normalized['amount'] = '2.95';
		$this->validate();
		$this->assertNotEmpty( $this->errors, 'No error for diminutive amount (BBD)' );

		$formattedMin = Amount::format( 3.00, 'BBD', 'en_US' );
		$expected = WmfFramework::formatMessage(
			'donate_interface-smallamount-error',
			$formattedMin
		);
		$this->assertEquals(
			$expected,
			$this->errors['amount'],
			'Wrong error message for diminutive amount (BBD)'
		);
	}
}
