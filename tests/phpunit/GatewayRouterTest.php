<?php

use MediaWiki\MediaWikiServices;

/**
 * @group DonationInterface
 */
class GatewayRouterTest extends MediaWikiIntegrationTestCase {

	/**
	 * Can we specify that a submethod supports recurring even if
	 * the method does not?
	 * @covers \MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter::getSupportedGateways
	 */
	public function testSubmethodOverridesMethod(): void {
		$this->overrideConfigValues( [
			'DonationInterfaceLocalConfigurationDirectory' => __DIR__ . '/data/routerTestConfig/',
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'gravy' => 'GravyAdapter',
			],
		] );

		/** @var \MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter $router */
		$router = MediaWikiServices::getInstance()->getService( 'DonationInterface.GatewayRouter' );
		$supportedGateways = $router->getSupportedGateways(
			'BR',
			'BRL',
			'cash',
			'fake_cash_submethod',
			true, // recurring
			null
		);
		$this->assertArrayEquals( [ 'gravy' ], $supportedGateways );
	}
}
