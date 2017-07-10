<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 * @group IngenicoApi
 * @group DonationInterfaceApi
 * @group medium
 */

class IngenicoApiTest extends DonationInterfaceApiTestCase {

	protected $hostedCheckoutProvider;

	protected $partialUrl;

	public function setUp() {
		parent::setUp();
		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();

		$providerConfig = TestingProviderConfiguration::createForProvider(
			'ingenico', $globalConfig
		);
		$ctx->providerConfigurationOverride = $providerConfig;

		$this->hostedCheckoutProvider = $this->getMockBuilder(
			'SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider'
		)->disableOriginalConstructor()->getMock();

		$providerConfig->overrideObjectInstance( 'payment-provider/cc', $this->hostedCheckoutProvider );
		$this->partialUrl = 'poweredbyglobalcollect.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:' .
			'8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0';
	}

	public function testGoodSubmit() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )->with(
				$this->callback( function( $actual ) {
					$hcsi = array(
						'locale' => 'en_US',
						'paymentProductFilters' => array(
							'restrictTo' => array(
								'products' => array(
									1
								)
							)
						),
						'showResultPage' => 'false'
					);
					$this->assertArraySubset( $hcsi, $actual['hostedCheckoutSpecificInput'] );
					$this->assertRegExp(
						'/Special:IngenicoGatewayResult/',
						$actual['hostedCheckoutSpecificInput']['returnUrl']
					);
					$order = array(
						'amountOfMoney' => array(
							'currencyCode' => 'USD',
							'amount' => 155
						),
						'customer' => array(
							'billingAddress' => array(
								'countryCode' => 'US',
								'city' => 'San Francisco',
								'state' => 'CA',
								'zip' => '94105',
								'street' => '123 Fake Street'
							),
							'contactDetails' => array(
								'emailAddress' => 'good@innocent.com'
							),
							'locale' => 'en_US',
							'personalInformation' => array(
								'name' => array(
									'firstName' => 'Firstname',
									'surname' => 'Surname'
								)
							)
						)
					);
					$this->assertArraySubset( $order, $actual['order'] );
					$this->assertTrue( is_numeric( $actual['order']['references']['merchantReference'] ) );
					return true;
				} )
			)
			->willReturn(
				array(
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				)
			);

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )->with(
				$this->equalTo( $this->partialUrl )
			)->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];

		$this->assertEquals(
			'https://wmf-pay.' . $this->partialUrl,
			$result['formaction'],
			'Ingenico API not setting formaction'
		);
		$this->assertTrue( $result['status'], 'Ingenico API result status should be true' );

		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );
		SourceFields::removeFromMessage( $message );
		$expected = array(
			'fee' => 0,
			'utm_source' => '..cc',
			'language' => 'en',
			'email' => 'good@innocent.com',
			'first_name' => 'Firstname',
			'last_name' => 'Surname',
			'country' => 'US',
			'gateway' => 'ingenico',
			'recurring' => '',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'currency' => 'USD',
			'gross' => 1.55,
			'user_ip' => '127.0.0.1',
			'street_address' => '123 Fake Street',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94105',
			'gateway_session_id' => '8915-28e5b79c889641c8ba770f1ba576c1fe'
		);
		$this->assertArraySubset( $expected, $message );
	}
}
