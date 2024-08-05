<?php

class BaseDlocalTestCase extends DonationInterfaceTestCase {

	protected $testAdapterClass = DlocalAdapter::class;
	protected $providerConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->providerConfig = $this->setSmashPigProvider( 'dlocal' );

		$this->overrideConfigValues( [
			'DlocalGatewayEnabled' => true,
			'DonationInterfaceEnableIPVelocityFilter' => true,
			'DonationInterfaceGatewayAdapters' => [
				'dlocal' => DlocalAdapter::class
			],
			'DlocalGatewayAccountInfo' => [
				'test' => [
					'dlocalScript' => 'placeholder-mock-script',
					'smartFieldApiKey' => 'placeholder-mock-key'
				]
			],
			// Set a controlled, very specific setting for 3DSRules. Since the gateway-specific
			// DlocalGateway3DSRules will override any DonationInterface3DSRules, make sure the
			// tests override any local settings.
			'DlocalGateway3DSRules' => [
				'BRL' => [ 'BR' ]
			],
		] );
	}
}
