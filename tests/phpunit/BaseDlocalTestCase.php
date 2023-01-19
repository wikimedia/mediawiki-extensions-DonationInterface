<?php

class BaseDlocalTestCase extends DonationInterfaceTestCase {

	protected $testAdapterClass = DlocalAdapter::class;
	protected $providerConfig;

	protected function setUp(): void {
		parent::setUp();
		$this->providerConfig = $this->setSmashPigProvider( 'dlocal' );

		$this->setMwGlobals( [
				'wgDlocalGatewayEnabled' => true,
				'wgDonationInterfaceEnableIPVelocityFilter' => true,
				'wgDonationInterfaceGatewayAdapters' => [
						'dlocal' => DlocalAdapter::class
				],
				'wgDlocalGatewayAccountInfo' => [
						'test' => [
										'dlocalScript' => 'placeholder-mock-script',
										'smartFieldApiKey' => 'placeholder-mock-key'
						]
				]
		] );
	}
}
