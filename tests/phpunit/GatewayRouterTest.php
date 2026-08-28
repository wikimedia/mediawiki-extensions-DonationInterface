<?php

use MediaWiki\Extension\DonationInterface\Special\GatewayRouter;
use MediaWiki\MediaWikiServices;

/**
 * @group DonationInterface
 */
class GatewayRouterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Can we specify that a submethod supports recurring even if
	 * the method does not?
	 * @covers \MediaWiki\Extension\DonationInterface\Special\GatewayRouter::getSupportedGateways
	 */
	public function testSubmethodOverridesMethod(): void {
		$this->overrideConfigValues( [
			'DonationInterfaceLocalConfigurationDirectory' => __DIR__ . '/data/routerTestConfig/',
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'gravy' => 'GravyAdapter',
			],
		] );
		$supportedGateways = GatewayRouter::getSupportedGateways(
			'BR',
			'BRL',
			'cash',
			'fake_cash_submethod',
			true, // recurring
			null,
			MediaWikiServices::getInstance()->getMainConfig()
		);
		$this->assertArrayEquals( [ 'gravy' ], $supportedGateways );
	}
}
