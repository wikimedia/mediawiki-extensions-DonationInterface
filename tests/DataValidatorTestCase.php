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
 * @group		Fundraising
 * @category	UnitTesting
 */
class DataValidatorTestCase  extends PHPUnit_Framework_TestCase {
	/**
	 * Test the Luhn check algorithm
	 * @dataProvider luhnDataProvider
	 */
	public function testLuhnCheck( $number, $expresult ) {
		$result = DataValidator::cc_number_exists_in_str( $number );
		$this->assertEquals( $expresult, $result, "$number failed expected luhn check" );
	}

	public function luhnDataProvider() {
		return array(
			// Mastercard
			array(5333331605740535, true),
			array("5143792293131636", true),
			array("John Doe 5199122553269905 Random", true),
			array("5497-8801-7320-5943", true),
			array("5370 5369 5295 3903", true),
			array(5295975049354398, true),
			array(5122728197617259, true),
			array(5372869474419840, true),
			array(5479089850576103, true),
			array(5375122664558457, true),

			// VISA array(16), digit
			array(4024007145540307, true),
			array(4532676809474030, true),
			array(4024007139174626, true),
			array(4556384391069166, true),
			array(4916423001204113, true),
			array(4716409516522919, true),
			array(4296465885589572, true),
			array(4532969094459737, true),
			array(4485480938896362, true),
			array(4539357366702682, true),

			// VISA array(13), digit
			array(4916199124929, true),
			array(4916237697951, true),
			array(4929247091115, true),
			array(4024007169572, true),
			array(4716716919391, true),

			// American Express
			array(343114236688284, true),
			array(379274178561225, true),

			// Discover
			array(6011013905647431, true),
			array(6011045341391380, true),
			array(6011324325736120, true),

			// Diners Club
			array(30343484937451, true),
			array(30037415730064, true),
			array(30392872026500, true),

			// enRoute
			array(201454799826249, true),
			array(201498205795993, true),
			array(214960886496931, true),

			// JCB
			array(3582219461343499, true),
			array(3534022982879267, true),
			array(3519002211673029, true),

			// Voyager
			array(869952786819898, true),
			array(869967184704708, true),
			array(869901879171733, true),

			// Not credit cards
			array("John Doe", false),
			array("Peter 123456", false),
			array(1234567, false)
		);
	}
}