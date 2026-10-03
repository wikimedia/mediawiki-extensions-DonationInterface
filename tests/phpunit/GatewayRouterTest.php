<?php

use Psr\Log\NullLogger;

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
		$router = $this->getServiceContainer()->getService( 'DonationInterface.GatewayRouter' );
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

	/**
	 * A payment method with country rules is only returned for the countries they allow.
	 * In the test config Venmo's country rules allow only the US and card has no
	 * country rules, so the US gets card and Venmo, and GB gets card only.
	 * @covers \MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter::getSupportedPaymentMethods
	 */
	public function testPaymentMethodsAreFilteredByCountry(): void {
		$this->overrideConfigValues( [
			'DonationInterfaceLocalConfigurationDirectory' => __DIR__ . '/data/paymentMethodsTestConfig/',
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'gravy' => 'GravyAdapter',
			],
		] );

		/** @var \MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter $router */
		$router = $this->getServiceContainer()->getService( 'DonationInterface.GatewayRouter' );

		$allowedGateways = [ 'gravy' ];
		$donorInUS = [ 'country' => 'US', 'currency' => 'USD', 'variant' => null ];
		$donorInGB = [ 'country' => 'GB', 'currency' => 'GBP', 'variant' => null ];

		$cardViaGravy = [ 'method' => 'cc', 'gateway' => 'gravy' ];
		$venmoViaGravy = [ 'method' => 'venmo', 'gateway' => 'gravy' ];

		$paymentMethodsInUS = $router->getSupportedPaymentMethods( $allowedGateways, $donorInUS, new NullLogger() );
		$paymentMethodsInGB = $router->getSupportedPaymentMethods( $allowedGateways, $donorInGB, new NullLogger() );

		$this->assertSame(
			[ $cardViaGravy, $venmoViaGravy ],
			$paymentMethodsInUS,
			'US donors get card and Venmo'
		);
		$this->assertSame(
			[ $cardViaGravy ],
			$paymentMethodsInGB,
			'GB donors get card only, as the country rules for Venmo allow only the US'
		);
	}
}
