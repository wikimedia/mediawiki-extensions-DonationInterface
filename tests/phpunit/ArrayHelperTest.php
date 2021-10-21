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
 * @group   ArrayHelper
 */
class ArrayHelperTest extends PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider getTestData
	 */
	public function testBuildRequestArray( $description, $input, $expected ) {
		$actual = ArrayHelper::buildRequestArray( $this->getCallback(), $input );
		$message = "buildRequestArray failed for $description";
		$this->assertEquals( $expected, $actual, $message );
	}

	public function getTestData() {
		return [
			// 1st test case
			[
				'flat array',
				// input
				[
					'double',
					'toil',
					'trouble'
				],
				// output
				[
					'double' => 'value for -double-',
					'toil' => 'value for -toil-',
					'trouble' => 'value for -trouble-',
				],
			],
			// 2nd test case
			[
				'nested array',
				// input
				[
					'fire' => [
						'burn',
					],
					'cauldron' => [
						'bubble',
					]
				],
				// output
				[
					'fire' => [
						'burn' => 'value for -burn-',
					],
					'cauldron' => [
						'bubble' => 'value for -bubble-',
					]
				],
			],
			// 3rd test case
			[
				'mixed array',
				// input
				[
					'poisoned entrails',
					'toad' => [
						'under_cold_stone',
						'days_and_nights',
						'thirty_one'
					],
				],
				// output
				[
					'poisoned entrails' => 'value for -poisoned entrails-',
					'toad' => [
						'under_cold_stone' => 'value for -under_cold_stone-',
						'days_and_nights' => 'value for -days_and_nights-',
						'thirty_one' => 'value for -thirty_one-'
					]
				],
			],
			// 4th test case
			[
				'omit empty',
				// input
				[
					'eye_of_newt',
					'toe_of_frog',
					'wool_of_bat', // callback returns '' for this value
					'tongue_of_dog',
				],
				// output
				[
					'eye_of_newt' => 'value for -eye_of_newt-',
					'toe_of_frog' => 'value for -toe_of_frog-',
					'tongue_of_dog' => 'value for -tongue_of_dog-'
				],
			],
			// 5th test case
			[
				'non-associative output',
				// input
				[
					'scale_of_dragon' => null,
					'tooth_of_wolf' => null
				],
				// output
				[
					'value for -scale_of_dragon-',
					'value for -tooth_of_wolf-'
				],
			]
		];
	}

	protected function getCallback() {
		return static function ( $key ) {
			if ( $key === 'wool_of_bat' ) {
				return '';
			}
			return "value for -$key-";
		};
	}
}
