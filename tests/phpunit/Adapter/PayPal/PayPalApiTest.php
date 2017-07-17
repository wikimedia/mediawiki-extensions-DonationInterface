<?php
use SmashPig\PaymentProviders\PayPal\Tests\PayPalTestConfiguration;
use SmashPig\Tests\TestingContext;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 * @group DonationInterfaceApi
 * @group medium
 */
class PayPalApiTest extends DonationInterfaceApiTestCase {

	public function setUp() {
		parent::setUp();
		$ctx = TestingContext::get();
		$ctx->providerConfigurationOverride = PayPalTestConfiguration::get(
			$ctx->getGlobalConfiguration()
		);
		$this->setMwGlobals( array(
			'wgDonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'wgPaypalExpressGatewayEnabled' => true,
			'wgDonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
		) );
	}

	public function testGoodSubmit() {
		$init = array(
			'amount' => 1.55,
			'currency' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'contribution_tracking_id' => strval( mt_rand() ),
			'language' => 'fr',
		);
		$init['gateway'] = 'paypal_ec';
		$init['action'] = 'donate';

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$expectedUrl = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U';
		$this->assertEquals( $expectedUrl, $result['formaction'], 'PayPal Express API not setting formaction' );
		$this->assertTrue( $result['status'], 'PayPal Express result status should be true' );
	}
}
