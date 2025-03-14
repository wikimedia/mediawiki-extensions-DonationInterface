<?php
use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\PayPal\PaymentProvider;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 * @group DonationInterfaceApi
 * @group medium
 * @coversNothing
 */
class PayPalApiTest extends DonationInterfaceApiTestCase {

	/**
	 * Mocked SmashPig-layer PaymentProvider object
	 * @var \PHPUnit\Framework\MockObject\MockObject|PaymentProvider
	 */
	private $provider;

	protected function setUp(): void {
		parent::setUp();
		$ctx = TestingContext::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$providerConfig = TestingProviderConfiguration::createForProvider( 'paypal', $globalConfig );
		$ctx->providerConfigurationOverride = $providerConfig;
		$this->provider = $this->createMock( PaymentProvider::class );
		$providerConfig->overrideObjectInstance( 'payment-provider/paypal', $this->provider );

		$this->overrideConfigValues( [
			'DonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'PaypalExpressGatewayEnabled' => true,
			'DonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
		] );
	}

	public function testGoodSubmit() {
		$init = [
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'language' => 'fr',
			'wmf_token' => $this->saltedToken,
		];
		$init['gateway'] = 'paypal_ec';
		$init['action'] = 'donate';
		$redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) {
				$this->assertStringContainsString(
					'title=Special:PaypalExpressGatewayResult&order_id=1.1' .
					'&wmf_token=' . urlencode( $this->saltedToken ),
					$params['return_url']
				);
				unset( $params['return_url'] );
				$this->assertEquals( [
					'cancel_url' => 'https://example.com/tryAgain.php/fr',
					'language' => 'fr_US',
					'description' => wfMessage( 'donate_interface-donation-description' )
						->inLanguage( 'fr' )
						->text(),
					'order_id' => '1.1',
					'amount' => '1.55',
					'currency' => 'USD',
				], $params );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setSuccessful( true )
					->setPaymentSession( 'EC-8US12345X1234567U' )
					->setRedirectUrl( $redirect )
			);

		$apiResult = $this->doApiRequest( $init, [ 'paypal_ecEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertArrayNotHasKey( 'errors', $result );

		$this->assertEquals( $redirect, $result['redirect'], 'PayPal Express API not setting redirect' );

		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotEmpty( $message, 'Missing pending message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = [
			'country' => 'US',
			'fee' => 0,
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => false,
			'language' => 'fr',
			'utm_source' => 'CD1234_FR..paypal',
			'currency' => 'USD',
			'email' => '',
			'gross' => '1.55',
			'recurring' => '',
			'response' => false,
			'utm_medium' => 'sitenotice',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'gateway_session_id' => 'EC-8US12345X1234567U',
			'user_ip' => '127.0.0.1',
		];
		// FIXME: want to assert stuff about countribution_tracking_id, but we
		// have no way of overriding that for API tests
		$this->assertArraySubmapSame(
			$expected,
			$message,
			'PayPal EC setup sending wrong pending message'
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending extra pending messages' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertCount( 1, $logged, 'Should have logged details once' );
		preg_match( '/Redirecting for transaction: (.*)$/', $logged[0], $matches );
		$detailString = $matches[1];
		$actual = json_decode( $detailString, true );
		$this->assertArraySubmapSame( $expected, $actual, 'Logged the wrong stuff!' );
	}

	public function testTooSmallDonation() {
		$init = [
			'amount' => 0.75,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'language' => 'fr',
			'wmf_token' => $this->saltedToken
		];
		$init['gateway'] = 'paypal_ec';
		$init['action'] = 'donate';

		$apiResult = $this->doApiRequest( $init, [ 'paypal_ecEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertNotEmpty( $result['errors'], 'Should have returned an error' );
		$this->assertNotEmpty( $result['errors']['amount'], 'Error should be in amount' );
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending pending message for error' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertSame( [], $logged, 'Logs are a lie, we did not redirect' );
	}

	public function testGoodRecurringSubmit() {
		$init = [
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'recurring' => '1',
			'contribution_tracking_id' => strval( mt_rand() ),
			'language' => 'fr',
			'wmf_token' => $this->saltedToken
		];
		$init['gateway'] = 'paypal_ec';
		$init['action'] = 'donate';
		$redirect = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->provider->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->callback( function ( $params ) {
				$this->assertStringContainsString(
					'title=Special:PaypalExpressGatewayResult&order_id=1.1' .
					'&wmf_token=' . urlencode( $this->saltedToken ),
					$params['return_url']
				);
				unset( $params['return_url'] );
				$this->assertEquals( [
					'cancel_url' => 'https://example.com/tryAgain.php/fr',
					'language' => 'fr_US',
					'description' => wfMessage( 'donate_interface-monthly-donation-description' )
						->inLanguage( 'fr' )
						->text(),
					'order_id' => '1.1',
					'amount' => '1.55',
					'currency' => 'USD',
					'recurring' => 1
				], $params );
				return true;
			} ) )
			->willReturn(
				( new CreatePaymentSessionResponse() )
					->setSuccessful( true )
					->setPaymentSession( 'EC-8US12345X1234567U' )
					->setRedirectUrl( $redirect )
			);

		$apiResult = $this->doApiRequest( $init, [ 'paypal_ecEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertArrayNotHasKey( 'errors', $result );

		$this->assertEquals( $redirect, $result['redirect'], 'PayPal Express API not setting redirect' );

		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNotEmpty( $message, 'Missing pending message' );
		DonationInterfaceTestCase::unsetVariableFields( $message );
		$expected = [
			'country' => 'US',
			'fee' => 0,
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => false,
			'language' => 'fr',
			'utm_source' => 'CD1234_FR..rpaypal',
			'currency' => 'USD',
			'email' => '',
			'gross' => '1.55',
			'recurring' => '1',
			'response' => false,
			'utm_medium' => 'sitenotice',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'gateway_session_id' => 'EC-8US12345X1234567U',
			'user_ip' => '127.0.0.1',
		];
		// FIXME: want to assert stuff about countribution_tracking_id, but we
		// have no way of overriding that for API tests
		$this->assertArraySubmapSame(
			$expected,
			$message,
			'PayPal EC setup sending wrong pending message'
		);
		$message = QueueWrapper::getQueue( 'pending' )->pop();
		$this->assertNull( $message, 'Sending extra pending messages' );
		$logged = DonationInterfaceTestCase::getLogMatches(
			LogLevel::INFO, '/^Redirecting for transaction: /'
		);
		$this->assertCount( 1, $logged, 'Should have logged details once' );
		preg_match( '/Redirecting for transaction: (.*)$/', $logged[0], $matches );
		$detailString = $matches[1];
		$actual = json_decode( $detailString, true );
		$this->assertArraySubmapSame( $expected, $actual, 'Logged the wrong stuff!' );
	}
}
