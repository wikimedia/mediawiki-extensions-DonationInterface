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
 * @category	UnitTesting
 */
class DataValidatorTest extends PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		DonationInterfaceTestCase::setUpSmashPigContext();
	}

	/**
	 * Test the Luhn check algorithm
	 * @dataProvider luhnDataProvider
	 */
	public function testLuhnCheck( $number, $expresult, $helpful_message ) {
		$result = DataValidator::cc_number_exists_in_str( $number );
		$this->assertEquals( $expresult, $result, "$number failed expected luhn check ($helpful_message)" );
	}

	public function luhnDataProvider() {
		return [
			// Mastercard
			[ '5333331605740535', true, 'Mastercard' ],
			[ '5143792293131636', true, 'Mastercard' ],
			[ 'John Doe 5199122553269905 Random', true, 'Mastercard' ],
			[ '5497-8801-7320-5943', true, 'Mastercard' ],
			[ '5370 5369 5295 3903', true, 'Mastercard' ],
			[ '5295975049354398', true, 'Mastercard' ],
			[ '5122728197617259', true, 'Mastercard' ],
			[ '5372869474419840', true, 'Mastercard' ],
			[ '5479089850576103', true, 'Mastercard' ],
			[ '5375122664558457', true, 'Mastercard' ],
			// VISA array(16), digit
			[ '4024007145540307', true, 'Visa 16 digit' ],
			[ '4532676809474030', true, 'Visa 16 digit' ],
			[ '4024007139174626', true, 'Visa 16 digit' ],
			[ '4556384391069166', true, 'Visa 16 digit' ],
			[ '4916423001204113', true, 'Visa 16 digit' ],
			[ '4716409516522919', true, 'Visa 16 digit' ],
			[ '4296465885589572', true, 'Visa 16 digit' ],
			[ '4532969094459737', true, 'Visa 16 digit' ],
			[ '4485480938896362', true, 'Visa 16 digit' ],
			[ '4539357366702682', true, 'Visa 16 digit' ],
			// VISA array(13), digit
			[ '4916199124929', true, 'Visa 13 digit' ],
			[ '4916237697951', true, 'Visa 13 digit' ],
			[ '4929247091115', true, 'Visa 13 digit' ],
			[ '4024007169572', true, 'Visa 13 digit' ],
			[ '4716716919391', true, 'Visa 13 digit' ],
			// American Express
			[ '343114236688284', true, 'Amex' ],
			[ '379274178561225', true, 'Amex' ],
			// Discover
			[ '6011013905647431', true, 'Discover' ],
			[ '6011045341391380', true, 'Discover' ],
			[ '6011324325736120', true, 'Discover' ],
			// Diners Club is not currently working at all
			// [ '30343484937451', true, 'Diners Club' ],
			// [ '30037415730064', true, 'Diners Club' ],
			// [ '30392872026500', true, 'Diners Club' ],
			// enRoute
			[ '201454799826249', true, 'enRoute' ],
			[ '201498205795993', true, 'enRoute' ],
			[ '214960886496931', true, 'enRoute' ],
			// JCB
			[ '3582219461343499', true, 'JCB' ],
			[ '3534022982879267', true, 'JCB' ],
			// not sure what is wrong with the next one, but it's failing
			// [ '3519002211673029', true, 'JCB' ],
			// Voyager is also not currently working at all
			// [ '869952786819898', true, 'Voyager' ],
			// [ '869967184704708', true, 'Voyager' ],
			// [ '869901879171733', true, 'Voyager' ],
			// Not credit cards
			[ 'John Doe', false, 'Not a valid credit card' ],
			[ 'Peter 123456', false, 'Not a valid credit card' ],
			[ '1234567', false, 'Not a valid credit card' ]
		];
	}

	/**
	 * Oh Shit: It's an actual simple unit test!
	 * @covers DataValidator::getZeroPaddedValue()
	 */
	public function testGetZeroPaddedValue() {
		// make sure that it works in the two main categories of ways it should work
		$this->assertSame( '00123', DataValidator::getZeroPaddedValue( '123', 5 ), "getZeroPaddedValue does not properly pad out a value in the simplest case" );
		$this->assertSame( '00123', DataValidator::getZeroPaddedValue( '0000123', 5 ), "getZeroPaddedValue does not properly unpad and re-pad a value when leading zeroes exist in the initial value" );

		// make sure it fails gracefully when asked to do something silly.
		$this->assertFalse( DataValidator::getZeroPaddedValue( '123456', 5 ), "getZeroPaddedValue does not return false when the exact desired value is impossible" );
	}

	public function fiscalNumberProvider() {
		return [
			[ 'BR', '', false ], // empty not OK for BR
			[ 'US', '', true ], // empty OK for US
			[ 'BR', '12345', false ], // too short for BR
			[ 'BR', '00003456789', true ],
			[ 'BR', '000.034.567-89', true ], // strip punctuation
			[ 'BR', '00.000.000/0001-00', true ], // CPNJ should pass too
			[ 'BR', '1111222233334444', false ],
			[ 'BR', 'ABC11122233', false ],
			[ 'CL', '12.123.123-K', true ],
			[ 'CL', '12.12.12-4', false ],
			[ 'CO', '123-456', true ],
			[ 'CO', '1234-5678-90', true ],
			[ 'CO', '12A-456-7', false ],
			[ 'CO', '1234-5678-901', false ],
			[ 'AR', 'ABC12312', false ],
			[ 'AR', '12341234', true ],
			[ 'AR', '12-34123412-1', true ], // 11 digit CUIT should pass
			[ 'AR', '1112223', true ],
			[ 'AR', '111222', false ],
			[ 'IN', 'AAAPL1234C', true ],
			[ 'IN', 'AA1PL1234C', false ],
			[ 'IN', 'AAAXL1234C', false ],
			[ 'MX', '', true ], // Not required for MX
		];
	}

	/**
	 * @dataProvider fiscalNumberProvider
	 * TODO: Test modular validator integration with DonationData
	 */
	public function testValidateFiscalNumber( $country, $value, $valid ) {
		$validator = new FiscalNumber();
		$errors = new ErrorState();
		$validator->validate(
			new TestingGenericAdapter(),
			[ 'country' => $country, 'fiscal_number' => $value, 'language' => 'en' ],
			$errors
		);
		$expectation = $valid ? "should" : "should not";
		$this->assertEquals(
			!$valid,
			$errors->hasValidationError( 'fiscal_number' ),
			"$value $expectation be a valid fiscal number for $country"
		);
	}

	public function employerProvider() {
		return [
			[ '4444 3333 2222 1111', true ],
			[ '3 mobile', false ],
			[ '4444 3333 2222 1111 mobile', true ],
		];
	}

	/**
	 * @dataProvider employerProvider
	 * TODO: Test modular validator integration with DonationData
	 */
	public function testValidateEmployerField( $value, $hasError ) {
		$validator = new EmployerFieldValidation();
		$errors = new ErrorState();
		$validator->validate(
			new TestingGenericAdapter(),
			[ 'employer' => $value ],
			$errors
		);
		$this->assertEquals(
			$hasError,
			$errors->hasValidationError( 'employer' ),
			"employer input should be invalid"
		);
	}
}
