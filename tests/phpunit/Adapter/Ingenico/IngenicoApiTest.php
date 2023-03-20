<?php

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
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

	protected function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgIngenicoGatewayHostedFormVariants' => [
				'iframe' => 105,
				'redirect' => 102,
			],
			'wgIngenicoGatewayEnabled' => true
		] );

		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();

		$providerConfig = TestingProviderConfiguration::createForProvider(
			'ingenico', $globalConfig
		);
		$ctx->providerConfigurationOverride = $providerConfig;

		$this->hostedCheckoutProvider = $this->createMock( HostedCheckoutProvider::class );

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
		$init['wmf_token'] = $this->saltedToken;

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )->with(
				$this->callback( function ( $actual ) {
					$hcsi = [
						'locale' => 'en_US',
						'paymentProductFilters' => [
							'restrictTo' => [
								'groups' => [
									'cards'
								]
							]
						],
						'showResultPage' => 'false',
						'variant' => 105
					];
					$this->assertArraySubmapSame( $hcsi, $actual['hostedCheckoutSpecificInput'] );
					$this->assertRegExp(
						'/Special:IngenicoGatewayResult/',
						$actual['hostedCheckoutSpecificInput']['returnUrl']
					);
					$order = [
						'amountOfMoney' => [
							'currencyCode' => 'USD',
							'amount' => 455.0
						],
						'customer' => [
							'billingAddress' => [
								'countryCode' => 'US',
								'city' => 'San Francisco',
								'state' => 'CA',
								'zip' => '94105',
								'street' => '123 Fake Street'
							],
							'contactDetails' => [
								'emailAddress' => 'good@innocent.com'
							],
							'locale' => 'en_US',
							'personalInformation' => [
								'name' => [
									'firstName' => 'Firstname',
									'surname' => 'Surname'
								]
							]
						]
					];
					$this->assertArraySubmapSame( $order, $actual['order'] );
					$this->assertTrue( is_numeric( $actual['order']['references']['merchantReference'] ) );
					return true;
				} )
			)
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )->with(
				$this->equalTo( $this->partialUrl )
			)->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$apiResult = $this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];

		$this->assertEquals(
			'https://wmf-pay.' . $this->partialUrl,
			$result['iframe'],
			'Ingenico API not setting iframe'
		);

		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotNull( $message, 'Not sending a message to the pending queue' );
		SourceFields::removeFromMessage( $message );
		$expected = [
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
			'gross' => '4.55',
			'user_ip' => '127.0.0.1',
			'street_address' => '123 Fake Street',
			'city' => 'San Francisco',
			'state_province' => 'CA',
			'postal_code' => '94105',
			'gateway_session_id' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
			'gateway_txn_id' => false,
		];
		$this->assertArraySubmapSame( $expected, $message );
	}

	public function testStageLocale() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['language'] = 'zh-ha';
		$init['wmf_token'] = $this->saltedToken;

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )->with(
				$this->callback( function ( $actual ) {
					$hcsi = [
						'locale' => 'zh_US',
						'paymentProductFilters' => [
							'restrictTo' => [
								'groups' => [
									'cards'
								]
							]
						],
						'showResultPage' => 'false',
						'variant' => 105
					];
					$this->assertArraySubmapSame( $hcsi, $actual['hostedCheckoutSpecificInput'] );
					return true;
				} )
			)
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )->with(
				$this->equalTo( $this->partialUrl )
			)->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
	}

	/**
	 * Don't mangle UTF-8 names when truncating data
	 */
	public function testNoMangleDataOnTruncate() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['first_name'] = 'ФёдорÐÐÐ';
		$init['last_name'] = 'Достоевский';
		$init['wmf_token'] = $this->saltedToken;

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )->with(
				$this->callback( function ( $actual ) use ( $init ) {
					$order = [
						'amountOfMoney' => [
							'currencyCode' => 'USD',
							'amount' => 455.0
						],
						'customer' => [
							'billingAddress' => [
								'countryCode' => 'US',
								'city' => 'San Francisco',
								'state' => 'CA',
								'zip' => '94105',
								'street' => '123 Fake Street'
							],
							'contactDetails' => [
								'emailAddress' => 'good@innocent.com'
							],
							'locale' => 'en_US',
							'personalInformation' => [
								'name' => [
									'firstName' => $init['first_name'],
									'surname' => $init['last_name']
								]
							]
						]
					];
					$this->assertArraySubmapSame( $order, $actual['order'] );
					return true;
				} )
			)
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )->with(
				$this->equalTo( $this->partialUrl )
			)->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
	}

	/**
	 * Submit payments with option to recur when variant like monthlyConvert*
	 * Note that the variant behavior under test here is handled in
	 * code and not in a variant config directory.
	 */
	public function testUpsellVariant() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['variant'] = 'monthlyConvert123';
		$init['wmf_token'] = $this->saltedToken;

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )->with(
				$this->callback( function ( $actual ) use ( $init ) {
					$this->assertArrayNotHasKey(
						'isRecurring', $actual['hostedCheckoutSpecificInput']
					);
					$cpmsi = [
						'tokenize' => true,
					];
					$this->assertArraySubmapSame( $cpmsi, $actual['cardPaymentMethodSpecificInput'] );
					return true;
				} )
			)
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);
		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )->with(
				$this->equalTo( $this->partialUrl )
			)->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
	}

	public function testSubmitFailInitialFilters() {
		$this->setInitialFiltersToFail();
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['wmf_token'] = $this->saltedToken;
		// Should not make any API calls
		$this->hostedCheckoutProvider->expects( $this->never() )
			->method( $this->anything() );

		$apiResult = $this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
	}

	/**
	 * This test is a general test of the optional field behaviour and not specific to
	 * ingenico, that sucks I know. It lives here because there was no better
	 * alternative home for it at the time. Ideally it will be moved to a newly created
	 * GatewatAdapterApiTest suite once we get that up and running.
	 *
	 * @group DonationInterfaceOptionalFields
	 *
	 */
	public function testOptionalFieldBehaviour() {
		$this->setMwGlobals( [
			'wgDonationInterfaceVariantConfigurationDirectory' =>
				__DIR__ . '/../includes/variants'
		] );

		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['variant'] = 'optional';
		$init['wmf_token'] = $this->saltedToken;

		// optional field 'last_name' present
		$init['first_name'] = 'Opty';
		$init['last_name'] = 'McPresent';

		$this->hostedCheckoutProvider->expects( $this->any() )
			->method( 'createHostedPayment' )
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);

		$this->hostedCheckoutProvider->expects( $this->any() )
			->method( 'getHostedPaymentUrl' )
			->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		// make request WITH optional field 'last_name' present
		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
		$message = QueueWrapper::getQueue( 'pending' )->pop();

		// check optional field present when supplied
		$this->assertEquals( 'McPresent', $message['last_name'] );

		// unset optional field 'last_name' and repeat request.
		$init['first_name'] = 'OptyPrince';
		unset( $init['last_name'] );
		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
		$message = QueueWrapper::getQueue( 'pending' )->pop();

		// check optional field not present
		$this->assertArrayNotHasKey( 'last_name', $message );
	}

	/**
	 * @group DonationInterfaceOptionalFields
	 */
	public function testSubmitEmployerField() {
		$this->setMwGlobals( [
			'wgDonationInterfaceVariantConfigurationDirectory' =>
				__DIR__ . '/../includes/variants'
		] );

		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['payment_submethod'] = 'visa';
		$init['gateway'] = 'ingenico';
		$init['action'] = 'donate';
		$init['variant'] = 'employer';
		$init['wmf_token'] = $this->saltedToken;

		// optional field 'last_name' present
		$init['employer'] = 'wikimedia foundation';

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'createHostedPayment' )
			->willReturn(
				[
					'partialRedirectUrl' => $this->partialUrl,
					'hostedCheckoutId' => '8915-28e5b79c889641c8ba770f1ba576c1fe',
					'RETURNMAC' => 'f5b66cf9-c64c-4c8d-8171-b47205c89a56'
				]
			);

		$this->hostedCheckoutProvider->expects( $this->once() )
			->method( 'getHostedPaymentUrl' )
			->willReturn( 'https://wmf-pay.' . $this->partialUrl );

		$this->doApiRequest( $init, [ 'ingenicoEditToken' => $this->clearToken ] );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertEquals( 'wikimedia foundation', $message['employer'] );
	}

	/**
	 * On a successful recurring conversion API call, we should
	 * send a subscr_signup message to the recurring queue.
	 */
	public function testRecurringConversionApiSuccess() {
		$donorTestData = DonationInterfaceTestCase::getDonorTestData();
		$donorTestData['email'] = 'good@innocent.com';
		$donorTestData['payment_method'] = 'cc';
		$donorTestData['payment_submethod'] = 'visa';
		$donorTestData['gateway'] = 'ingenico';
		$donorTestData['variant'] = 'monthlyConvert';
		$donorTestData['recurring_payment_token'] = 'T1234-5432-9876';
		$donorTestData['gateway_txn_id'] = 'TXN-999-1234';
		$session = [
			'Donor' => $donorTestData,
			'ingenicoEditToken' => $this->clearToken,
		];

		$apiParams = [
			'amount' => '1.22',
			'action' => 'di_recurring_convert',
			'gateway' => 'ingenico',
			'wmf_token' => $this->saltedToken,
		];

		$apiResult = $this->doApiRequest( $apiParams, $session );
		$result = $apiResult[0]['result'];
		$this->assertArrayNotHasKey( 'errors', $result );

		$message = QueueWrapper::getQueue( 'recurring' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = array_merge( $donorTestData, [
			'txn_type' => 'subscr_signup',
			'frequency_unit' => 'month',
			'frequency_interval' => 1,
		] );
		unset( $expected['amount'] );
		unset( $expected['referrer'] );
		unset( $expected['processor_form'] );
		unset( $expected['variant'] );
		$expected['gross'] = '1.22';
		$this->assertArraySubmapSame( $expected, $message );
	}

	/**
	 * If there's no token in session, the recurring conversion should return
	 * an error and we shouldn't send anything to the recurring queue.
	 */
	public function testRecurringConversionApiError() {
		$donorTestData = DonationInterfaceTestCase::getDonorTestData();
		$donorTestData['email'] = 'good@innocent.com';
		$donorTestData['payment_method'] = 'cc';
		$donorTestData['payment_submethod'] = 'visa';
		$donorTestData['gateway'] = 'ingenico';
		$donorTestData['variant'] = 'monthlyConvert';
		$session = [
			'Donor' => $donorTestData,
			'ingenicoEditToken' => $this->clearToken,
		];

		$apiParams = [
			'amount' => '1.22',
			'action' => 'di_recurring_convert',
			'gateway' => 'ingenico',
			'wmf_token' => $this->saltedToken,
		];

		$apiResult = $this->doApiRequest( $apiParams, $session );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'] );

		$message = QueueWrapper::getQueue( 'recurring' )->pop();
		$this->assertNull( $message );
	}
}
