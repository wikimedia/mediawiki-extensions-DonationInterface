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

use PHPUnit\Framework\MockObject\MockObject;
use SmashPig\PaymentProviders\Ingenico\BankPaymentProvider;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 * @group RealTimeBankTransfer
 */
class DonationInterface_Adapter_Ingenico_RealTimeBankTransferIdealTest extends BaseIngenicoTestCase {
	/**
	 * @var MockObject
	 */
	protected $bankPaymentProvider;

	public function setUp(): void {
		parent::setUp();
		$this->markTestSkipped( 'RTBT not implemented' );
		$config = TestingProviderConfiguration::createForProvider(
			'ingenico',
			self::$smashPigGlobalConfig
		);
		TestingContext::get()->providerConfigurationOverride = $config;

		$this->bankPaymentProvider = $this->getMockBuilder(
			BankPaymentProvider::class
		)->disableOriginalConstructor()->getMock();

		$config->overrideObjectInstance( 'payment-provider/rtbt', $this->bankPaymentProvider );

		$this->bankPaymentProvider->method( 'getBankList' )
			->willReturn(
				[
					'Test1234' => 'Test Bank 1234',
					'Test5678' => 'Test Bank 5678',
				]
			);

		$this->setMwGlobals(
			[
				'wgIngenicoGatewayEnabled' => true,
			]
		);
	}

	/**
	 * Test for ideal form loading
	 */
	public function testIngenicoFormLoad_rtbt_Ideal() {
		$init = $this->getDonorTestData( 'NL' );
		unset( $init['order_id'] );
		$init['payment_method'] = 'rtbt';

		$assertNodes = [
			'amount' => [
				'nodename' => 'input',
				'value' => '1.55',
			],
			'currency' => [
				'nodename' => 'select',
				'selected' => 'EUR',
			],
			'country' => [
				'nodename' => 'input',
				'value' => 'NL',
			],
			'issuer_id' => [
				'innerhtmlmatches' => '/Test Bank 1234/'
			]
		];

		$this->verifyFormOutput( 'IngenicoGateway', $init, $assertNodes, true );
	}

}
