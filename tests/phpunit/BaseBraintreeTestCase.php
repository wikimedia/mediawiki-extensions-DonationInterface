<?php
class BaseBraintreeTestCase extends DonationInterfaceTestCase {

	/** @inheritDoc */
	protected $testAdapterClass = BraintreeAdapter::class;
	/**
	 * @var \SmashPig\Tests\TestingProviderConfiguration
	 */
	protected $providerConfig;

	public function setUp(): void {
		parent::setUp();

		$this->providerConfig = $this->setSmashPigProvider( 'braintree' );

		$this->overrideConfigValues( [
			'BraintreeGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'braintree' => BraintreeAdapter::class
			],
		] );
	}

	protected function getTestDonor( $payment_method ): array {
		$init = $this->getDonorTestData();
		$init['payment_method'] = $payment_method;
		$init['payment_token'] = '65a502e5-2d09-02bd-545f-1cf6e15867c9';
		$init['contribution_tracking_id'] = (string)mt_rand( 1000000, 10000000 );
		unset( $init['city'] );
		unset( $init['state_province'] );
		unset( $init['street_address'] );
		unset( $init['postal_code'] );
		unset( $init['first_name'] );
		unset( $init['last_name'] );
		return $init;
	}
}
