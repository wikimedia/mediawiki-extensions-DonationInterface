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
use Psr\Log\LogLevel;
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group DIIntegration
 * @coversNothing
 */
class IntegrationTest extends DonationInterfaceTestCase {

	/**
	 * @param string|null $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;

		parent::__construct( $name, $data, $dataName );
	}

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'DlocalGatewayEnabled' => true,
			'PaypalExpressGatewayEnabled' => true,
		] );
	}

	/**
	 * This is meant to simulate a user choosing PayPal, then going back and choosing DLocal.
	 */
	public function testBackClickPayPalToDlocal() {
		$options = $this->getDonorTestData( 'MX' );
		$options['payment_method'] = 'paypal';
		$paypalRequest = $this->setUpRequest( $options );

		$providerConfig = TestingProviderConfiguration::createForProvider(
			'paypal', self::$smashPigGlobalConfig
		);
		$provider = $this->createMock( PaymentProvider::class );
		$providerConfig->overrideObjectInstance( 'payment-provider/paypal', $provider );
		TestingContext::get()->providerConfigurationOverride = $providerConfig;
		$provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setRawResponse(
						'TOKEN=EC%2d8US12345X1234567U&TIMESTAMP=2017%2d05%2d18T14%3a53%3a29Z&CORRELATIONID=' .
						'6d987654a7aed&ACK=Success&VERSION=204&BUILD=33490839'
					)
					->setSuccessful( true )
					->setPaymentSession( 'EC-8US12345X1234567U' )
					->setRedirectUrl( 'https://example.com/foo' )
			);

		$gateway = new PaypalExpressAdapter();
		$gateway->doPayment();
		$paypalCtId = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		// now, get dlocal.
		$options['payment_method'] = 'cc';
		$options['payment_submethod'] = 'visa';
		$this->setUpRequest( $options, $paypalRequest->getSessionArray() );

		$this->mockGenericPaymentProviderForCreatePayment( 'dlocal', 'cc' );
		$gateway = new TestingDlocalAdapter();
		$gateway->doPayment();
		$dlocalPayCtId = $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$this->assertNotEquals( $dlocalPayCtId, $paypalCtId, 'Did not regenerate contribution tracking ID on gateway switch' );

		$errors = '';
		$messages = DonationLoggerFactory::$overrideLogger->messages;
		if ( array_key_exists( LogLevel::ERROR, $messages ) ) {
			foreach ( $messages[LogLevel::ERROR] as $msg ) {
				$errors .= "$msg\n";
			}
		}
		$this->assertSame( '', $errors, "The gateway error log had the following message(s):\n" . $errors );
	}

}
