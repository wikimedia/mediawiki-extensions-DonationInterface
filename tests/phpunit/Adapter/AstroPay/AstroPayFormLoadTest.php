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
 *
 * @group Fundraising
 * @group DonationInterface
 * @group AstroPay
 */
class AstroPayFormLoadTest extends DonationInterfaceTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgAstroPayGatewayEnabled' => true,
		] );
	}

	public function testAstroPayFormLoad() {
		$init = $this->getDonorTestData( 'CO' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';

		$assertNodes = [
			'submethod-visa' => [
				'nodename' => 'input'
			],
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace( '$', '\$',
						Amount::format( 5, 'COP', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
			'fiscal_number' => [
				'nodename' => 'input',
				'value' => '9.999.999.999',
			],
		];

		$this->verifyFormOutput( 'AstroPayGateway', $init, $assertNodes, true );
	}

	public function testAstroPayFormLoad_IN() {
		$init = $this->getDonorTestData( 'IN' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'bt';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 100, 'INR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'IN',
			],
		];

		$this->verifyFormOutput( 'AstroPayGateway', $init, $assertNodes, true );
	}

	public function testAstroPayFormLoadSinlgePresetSubmethod_IN() {
		$init = $this->getDonorTestData( 'IN' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'bt';
		$init['payment_submethod'] = 'netbanking';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					Amount::format( 100, 'INR', $init['language'] . '_' . $init['country'] ) .
					'\s*$/',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'IN',
			],
			'submethod-netbanking' => [
				'nodename' => 'input',
				'nodehtmlmatches' => '/checked/',
			],
		];

		$this->verifyFormOutput( 'AstroPayGateway', $init, $assertNodes, true );
	}

}
