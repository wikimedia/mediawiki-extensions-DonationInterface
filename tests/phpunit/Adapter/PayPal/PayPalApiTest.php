<?php
use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Tests\TestingContext;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 * @group DonationInterfaceApi
 * @group medium
 */
class PayPalApiTest extends DonationInterfaceApiTestCase {

	public function setUp(): void {
		parent::setUp();
		$ctx = TestingContext::get();
		$ctx->providerConfigurationOverride = TestingProviderConfiguration::createForProvider(
			'paypal', $ctx->getGlobalConfiguration()
		);
		$this->setMwGlobals( [
			'wgDonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'wgPaypalExpressGatewayEnabled' => true,
			'wgDonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
		] );
	}

	public function testGoodSubmit() {
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( 'OK' );
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

		$apiResult = $this->doApiRequest( $init, [ 'paypal_ecEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );

		$expectedUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->assertEquals( $expectedUrl, $result['redirect'], 'PayPal Express API not setting redirect' );

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
		$this->assertEmpty( $logged, 'Logs are a lie, we did not redirect' );
	}

	public function testGoodRecurringSubmit() {
		TestingPaypalExpressAdapter::setDummyGatewayResponseCode( 'OK' );
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

		$apiResult = $this->doApiRequest( $init, [ 'paypal_ecEditToken' => $this->clearToken ] );
		$result = $apiResult[0]['result'];
		$this->assertTrue( empty( $result['errors'] ) );

		$expectedUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U&useraction=commit';
		$this->assertEquals( $expectedUrl, $result['redirect'], 'PayPal Express API not setting redirect' );

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
