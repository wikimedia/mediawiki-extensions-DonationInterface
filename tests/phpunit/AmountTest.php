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

use SmashPig\Core\ValidationError;

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
	 * @var ErrorState
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
			'currency' => 'USD',
		);

		$this->errors = new ErrorState();
		$this->adapter = new TestingGenericAdapter();
		$this->validator = new Amount();
	}

	protected function validate() {
		$this->validator->validate(
			$this->adapter, $this->normalized, $this->errors
		);
	}

	protected function getFirstError() {
		$list = $this->errors->getErrors();
		return $list[0];
	}

	public function testValidUsd() {
		$this->normalized['amount'] = '10.00';
		$this->validate();
		$this->assertFalse(
			$this->errors->hasValidationError(),
			'Error shown for valid amount'
		);
	}

	public function testZeroAmount() {
		$this->normalized['amount'] = '0.00';
		$this->validate();
		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for zero amount'
		);
		$expected = DataValidator::getError( 'amount', 'not_empty' );
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error for zero amount'
		);
	}

	public function testWhitespaceAmount() {
		$this->normalized['amount'] = '    ';
		$this->validate();
		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for whitespace amount'
		);
		$expected = DataValidator::getError( 'amount', 'not_empty' );
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error for zero amount'
		);
	}

	public function testNonNumericAmount() {
		$this->normalized['amount'] = 'XYZ123';
		$this->validate();
		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for non-numeric amount'
		);

		$expected = new ValidationError(
			'amount',
			'donate_interface-error-msg-invalid-amount'
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for non-numeric amount'
		);
	}

	public function testNegativeAmount() {
		$this->normalized['amount'] = '-100.00';
		$this->validate();
		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for negative amount'
		);

		$expected = new ValidationError(
			'amount',
			'donate_interface-error-msg-invalid-amount'
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for non-numeric amount'
		);
	}

	public function testTooMuchUsd() {
		$this->normalized['amount'] = '101.00';
		$this->validate();
		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for excessive amount (USD)'
		);
		$expected = new ValidationError(
			'amount',
			'donate_interface-bigamount-error',
			array(
				100,
				'USD',
				$this->adapter->getGlobal( 'MajorGiftsEmail' ),
			)
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for excessive amount (USD)'
		);
	}

	public function testTooLittleUsd() {
		$this->normalized['amount'] = '1.49';
		$this->validate();

		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for diminutive amount (USD)'
		);

		$formattedMin = Amount::format( 1.50, 'USD', 'en_US' );
		$expected = new ValidationError(
			'amount',
			'donate_interface-smallamount-error',
			array( $formattedMin )
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for diminutive amount (USD)'
		);
	}

	// Conversion tests depend on Barbadian monetary policy
	// BBD is convenient as it's pegged to $0.50
	public function testTooMuchBbd() {
		$this->normalized['currency'] = 'BBD';
		$this->normalized['amount'] = '201.00';
		$this->validate();

		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for excessive amount (BBD)'
		);
		$expected = new ValidationError(
			'amount',
			'donate_interface-bigamount-error',
			array(
				200,
				'BBD',
				$this->adapter->getGlobal( 'MajorGiftsEmail' )
			)
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for excessive amount (BBD)'
		);
	}

	public function testTooLittleBbd() {
		$this->normalized['currency'] = 'BBD';
		$this->normalized['amount'] = '2.95';
		$this->validate();

		$this->assertTrue(
			$this->errors->hasValidationError( 'amount' ),
			'No error for diminutive amount (BBD)'
		);
		$formattedMin = Amount::format( 3.00, 'BBD', 'en_US' );
		$expected = new ValidationError(
			'amount',
			'donate_interface-smallamount-error',
			array( $formattedMin )
		);
		$this->assertEquals(
			$expected,
			$this->getFirstError(),
			'Wrong error message for diminutive amount (BBD)'
		);
	}

	public function testRoundNoDigit() {
		$rounded = Amount::round( '100.01', 'JPY' );
		$this->assertEquals( 100, $rounded );
	}

	public function testRoundTwoDigit() {
		$rounded = Amount::round( '2.762', 'CAD' );
		$this->assertEquals( 2.76, $rounded );
	}

	public function testRoundThreeDigit() {
		$rounded = Amount::round( '19.5437', 'KWD' );
		$this->assertEquals( 19.544, $rounded );
	}

	public function testFormat() {
		if ( !class_exists( NumberFormatter::class ) ) {
			$this->markTestSkipped( 'No NumberFormatter present' );
		}
		$validator = new Amount();
		$amount = $validator->format( '100.59', 'USD', 'en-US' );
		$this->assertEquals( '$100.59', $amount );

		$amount = $validator->format( 100.59, 'USB', null );
		$this->assertEquals( 'USB100.59', $amount );

		$amount = $validator->format( 100.59, 'USD', 'en_CA' );
		$this->assertEquals( 'US$100.59', $amount );
	}
}
