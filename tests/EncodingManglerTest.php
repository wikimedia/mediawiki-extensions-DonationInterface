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
 * @group	EncodingMangler
 * @category	UnitTesting
 */
class EncodingManglerTest extends PHPUnit_Framework_TestCase {

	/**
	 * Keep the accented characters that we can represent
	 */
	public function testRetainsAccents() {
		$input = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ';
		$output = EncodingMangler::singleton()->transliterate( $input );
		$this->assertEquals( $input, $output );
	}

	/**
	 * Make eastern European characters NATO-friendly
	 */
	public function testMangleCzech() {
		$output = EncodingMangler::singleton()->transliterate( 'ČčŘřŠšŽž' );
		$this->assertEquals( 'CcRrSsZz', $output );
	}

	/**
	 * Ditto for Turkish
	 */
	public function testMangleTurkish() {
		$output = EncodingMangler::singleton()->transliterate( 'İĞğŞş' );
		$this->assertEquals( 'IGgSs', $output );
	}

}
