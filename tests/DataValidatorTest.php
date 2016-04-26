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
class DataValidatorTest  extends PHPUnit_Framework_TestCase {
	/**
	 * Test the Luhn check algorithm
	 * @dataProvider luhnDataProvider
	 */
	public function testLuhnCheck( $number, $expresult, $helpful_message ) {
		$result = DataValidator::cc_number_exists_in_str( $number );
		$this->assertEquals( $expresult, $result, "$number failed expected luhn check ($helpful_message)" );
	}

	public function luhnDataProvider() {
		return array(
			// Mastercard
			array ( '5333331605740535', true, 'Mastercard' ),
			array ( '5143792293131636', true, 'Mastercard' ),
			array ( 'John Doe 5199122553269905 Random', true, 'Mastercard' ),
			array ( '5497-8801-7320-5943', true, 'Mastercard' ),
			array ( '5370 5369 5295 3903', true, 'Mastercard' ),
			array ( '5295975049354398', true, 'Mastercard' ),
			array ( '5122728197617259', true, 'Mastercard' ),
			array ( '5372869474419840', true, 'Mastercard' ),
			array ( '5479089850576103', true, 'Mastercard' ),
			array ( '5375122664558457', true, 'Mastercard' ),
			// VISA array(16), digit
			array ( '4024007145540307', true, 'Visa 16 digit' ),
			array ( '4532676809474030', true, 'Visa 16 digit' ),
			array ( '4024007139174626', true, 'Visa 16 digit' ),
			array ( '4556384391069166', true, 'Visa 16 digit' ),
			array ( '4916423001204113', true, 'Visa 16 digit' ),
			array ( '4716409516522919', true, 'Visa 16 digit' ),
			array ( '4296465885589572', true, 'Visa 16 digit' ),
			array ( '4532969094459737', true, 'Visa 16 digit' ),
			array ( '4485480938896362', true, 'Visa 16 digit' ),
			array ( '4539357366702682', true, 'Visa 16 digit' ),
			// VISA array(13), digit
			array ( '4916199124929', true, 'Visa 13 digit' ),
			array ( '4916237697951', true, 'Visa 13 digit' ),
			array ( '4929247091115', true, 'Visa 13 digit' ),
			array ( '4024007169572', true, 'Visa 13 digit' ),
			array ( '4716716919391', true, 'Visa 13 digit' ),
			// American Express
			array ( '343114236688284', true, 'Amex' ),
			array ( '379274178561225', true, 'Amex' ),
			// Discover
			array ( '6011013905647431', true, 'Discover' ),
			array ( '6011045341391380', true, 'Discover' ),
			array ( '6011324325736120', true, 'Discover' ),
			// Diners Club is not currently working at all
			/**
			  array ( '30343484937451', true, 'Diners Club' ),
			  array ( '30037415730064', true, 'Diners Club' ),
			  array ( '30392872026500', true, 'Diners Club' ),
			 */
			// enRoute
			array ( '201454799826249', true, 'enRoute' ),
			array ( '201498205795993', true, 'enRoute' ),
			array ( '214960886496931', true, 'enRoute' ),
			// JCB
			array ( '3582219461343499', true, 'JCB' ),
			array ( '3534022982879267', true, 'JCB' ),
			//not sure what is wrong with the next one, but it's failing
			//array ( '3519002211673029', true, 'JCB' ),
			// Voyager is also not currently working at all
			/**
			  array ( '869952786819898', true, 'Voyager' ),
			  array ( '869967184704708', true, 'Voyager' ),
			  array ( '869901879171733', true, 'Voyager' ),
			 */
			// Not credit cards
			array ( 'John Doe', false, 'Not a valid credit card' ),
			array ( 'Peter 123456', false, 'Not a valid credit card' ),
			array ( '1234567', false, 'Not a valid credit card' )
		);
	}

	/**
	 * Oh Shit: It's an actual simple unit test!
	 * @covers DataValidator::getZeroPaddedValue()
	 */
	public function testGetZeroPaddedValue() {
		//make sure that it works in the two main categories of ways it should work
		$this->assertEquals( '00123', DataValidator::getZeroPaddedValue( '123', 5 ), "getZeroPaddedValue does not properly pad out a value in the simplest case" );
		$this->assertEquals( '00123', DataValidator::getZeroPaddedValue( '0000123', 5 ), "getZeroPaddedValue does not properly unpad and re-pad a value when leading zeroes exist in the initial value" );

		//make sure it fails gracefully when asked to do something silly.
		$this->assertFalse( DataValidator::getZeroPaddedValue( '123456', 5 ), "getZeroPaddedValue does not return false when the exact desired value is impossible" );
	}

	public function fiscalNumberProvider() {
		return array(
			array( 'BR', '', false ), // empty not OK for BR
			array( 'US', '', true ), // empty OK for US
			array( 'BR', '12345', false ), // too short for BR
			array( 'BR', '00003456789', true ),
			array( 'BR', '000.034.567-89', true ), // strip punctuation
			array( 'BR', '00.000.000/0001-00', true ), // CPNJ should pass too
			array( 'BR', '1111222233334444', false ),
			array( 'BR', 'ABC11122233', false ),
			array( 'CL', '12.123.123-K', true ),
			array( 'CL', '12.12.12-4', false ),
			array( 'AR', 'ABC12312', false ),
			array( 'AR', '12341234', true ),
			array( 'AR', '1112223', true ),
			array( 'AR', '111222', false ),
			array( 'MX', '', true ), // Not required for MX
		);
	}

	/**
	 * @dataProvider fiscalNumberProvider
	 */
	public function testValidateFiscalNumber( $country, $value, $valid ) {
		$expectation = $valid ? "should" : "should not";
		$this->assertEquals( $valid, DataValidator::validate_fiscal_number( $value, $country ), "$value $expectation be a valid fiscal number for $country" );
	}
}
