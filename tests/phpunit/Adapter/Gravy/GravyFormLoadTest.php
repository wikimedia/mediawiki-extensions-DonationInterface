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

use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Gravy
 */
class GravyFormLoadTest extends BaseGravyTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->cardPaymentProvider = $this->createMock( CardPaymentProvider::class );

		$this->providerConfig->overrideObjectInstance(
			'payment-provider/cc',
			$this->cardPaymentProvider
		);
		$this->cardPaymentProvider->expects( $this->any() )
			->method( 'createPaymentSession' )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setSuccessful( true )
					->setPaymentSession( 'lorem-ipsum' )
			);
	}

	public function testGravyFormLoad() {
		$init = $this->getDonorTestData( 'US' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'gravy';

		$assertNodes = [
			'selected-amount' => [
				'nodename' => 'span',
				'innerhtmlmatches' => '/^\s*' .
					str_replace(
						'$',
						'\$',
						Amount::format( 4.55, 'USD', $init['language'] . '_' . $init['country'] )
					) .
					'\s*$/',
			],
		];

		$this->verifyFormOutput( 'GravyGateway', $init, $assertNodes, true );
	}

}
