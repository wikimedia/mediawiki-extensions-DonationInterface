<?php
/**
 * 'TIMESTAMP=2016%2d05%2d02T19%3a58%3a19Z&CORRELATIONID=b33e6ff7eba&ACK=Failure&VERSION=0%2e000000&BUILD=21669447&L_ERRORCODE0=10002&L_SHORTMESSAGE0=Authentication%2fAuthorization%20Failed&L_LONGMESSAGE0=You%20do%20not%20have%20permissions%20to%20make%20this%20API%20call&L_SEVERITYCODE0=Error'
 *
 * TOKEN=EC%2d1YM52022PV490383V&PHONENUM=408%2d123%2d4567&BILLINGAGREEMENTACCEPTEDSTATUS=0&CHECKOUTSTATUS=PaymentActionNotInitiated&TIMESTAMP=2016%2d05%2d03T19%3a57%3a56Z&CORRELATIONID=c3811aeb1e7f5&ACK=Success&VERSION=124&BUILD=21669447&EMAIL=fr%2dtech%2bdonor%40wikimedia%2eorg&PAYERID=FLJLQ2GV38E4Y&PAYERSTATUS=verified&FIRSTNAME=f&LASTNAME=doner&COUNTRYCODE=US&ADDRESSSTATUS=Confirmed&CURRENCYCODE=JPY&AMT=500&ITEMAMT=500&SHIPPINGAMT=0&HANDLINGAMT=0&TAXAMT=0&CUSTOM=4116&DESC=Donation%20to%20the%20Wikimedia%20Foundation&INVNUM=4116&INSURANCEAMT=0&SHIPDISCAMT=0&INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_CURRENCYCODE=JPY&PAYMENTREQUEST_0_AMT=500&PAYMENTREQUEST_0_ITEMAMT=500&PAYMENTREQUEST_0_SHIPPINGAMT=0&PAYMENTREQUEST_0_HANDLINGAMT=0&PAYMENTREQUEST_0_TAXAMT=0&PAYMENTREQUEST_0_CUSTOM=4116&PAYMENTREQUEST_0_DESC=Donation%20to%20the%20Wikimedia%20Foundation&PAYMENTREQUEST_0_INVNUM=4116&PAYMENTREQUEST_0_INSURANCEAMT=0&PAYMENTREQUEST_0_SHIPDISCAMT=0&PAYMENTREQUEST_0_SELLERPAYPALACCOUNTID=fr%2dtech%2dfacilitator%40wikimedia%2eorg&PAYMENTREQUEST_0_INSURANCEOPTIONOFFERED=false&PAYMENTREQUEST_0_ADDRESSSTATUS=Confirmed&PAYMENTREQUESTINFO_0_ERRORCODE=0
 *
 * TIMESTAMP=2016%2d05%2d03T21%3a43%3a20Z&CORRELATIONID=f624ed5aa5db0&ACK=Failure&VERSION=124&BUILD=21669447&L_ERRORCODE0=10412&L_SHORTMESSAGE0=Duplicate%20invoice&L_LONGMESSAGE0=Payment%20has%20already%20been%20made%20for%20this%20InvoiceID%2e&L_SEVERITYCODE0=Error
 */

/**
 *
 * @group Fundraising
 * @group DonationInterface
 * @group PayPal
 */
class DonationInterface_Adapter_PayPal_Express_Test extends DonationInterfaceTestCase {

	protected $testAdapterClass = 'TestingPaypalExpressAdapter';

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals( array(
			'wgDonationInterfaceCancelPage' => 'https://example.com/tryAgain.php',
			'wgPaypalExpressGatewayEnabled' => true,
			'wgDonationInterfaceThankYouPage' => 'https://example.org/wiki/Thank_You',
		) );
	}

	protected function unsetVariableFields( &$message ) {
		$fields = array(
			'date', 'source_host', 'source_run_id', 'source_version', 'gateway_account'
		);
		foreach ( $fields as $field ) {
			unset( $message[$field] );
		}
	}

	function testPaymentSetup() {
		$init = array(
			'amount' => 1.55,
			'currency_code' => 'USD',
			'payment_method' => 'paypal',
			'utm_source' => 'CD1234_FR',
			'utm_medium' => 'sitenotice',
			'country' => 'US',
			'contribution_tracking_id' => strval( mt_rand() ),
			'language' => 'fr',
		);
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( 'OK' );
		$result = $gateway->doPayment();
		$gateway->logPending(); // GatewayPage calls this for redirects
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-8US12345X1234567U',
			$result->getRedirect(),
			'Wrong redirect for PayPal EC payment setup'
		);
		$message = DonationQueue::instance()->pop( 'pending' );
		$this->assertNotEmpty( $message, 'Missing pending message' );
		$this->unsetVariableFields( $message );
		$expected = array(
			'country' => 'US',
		    'fee' => '0',
		    'gateway' => 'paypal_ec',
		    'gateway_txn_id' => null,
		    'language' => 'fr',
		    'contribution_tracking_id' => $init['contribution_tracking_id'],
		    'order_id' => $init['contribution_tracking_id'] . '.0',
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
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		);
		$this->assertEquals(
			$expected,
			$message,
			'PayPal EC setup sending wrong pending message'
		);
	}

	/**
	 * Check that the adapter makes the correct calls for successful donations
	 * and sends a good queue message.
	 */
	function testProcessDonorReturn() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( 'OK' );
		$gateway->processDonorReturn( array(
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD'
		) );

		$message = DonationQueue::instance()->pop( 'complete' );
		$this->assertNotNull( $message, 'Not sending a message to the complete queue' );
		$this->unsetVariableFields( $message );
		$expected = array (
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => '5EJ123456T987654S',
			'gateway_session_id' => 'EC-4V987654XA123456V',
			'language' => 'en',
			'order_id' => $init['contribution_tracking_id'] . '.0',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'response' => false,
			'user_ip' => '127.0.0.1',
			'utm_source' => '..paypal',
			'city' => 'San Francisco',
			'currency' => 'USD',
			'email' => 'donor@generous.net',
			'first_name' => 'Fezziwig',
			'gross' => '1.55',
			'last_name' => 'Fowl',
			'recurring' => '',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
		);
		$this->assertEquals( $expected, $message );

		$this->assertNull(
			DonationQueue::instance()->pop( 'complete' ),
			'Sending extra messages to complete queue!'
		);
	}

	public function testProcessDonorReturnRecurring() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['recurring'] = '1';
		$this->setUpRequest( $init, array( 'Donor' => $init ) );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( 'Recurring-OK' );
		$gateway->processDonorReturn( array(
			'token' => 'EC%2d4V987654XA123456V',
			'PayerID' => 'ASDASD'
		) );

		$message = DonationQueue::instance()->pop( 'complete' );
		$this->assertNotNull( $message, 'Not sending a message to the complete queue' );
		$this->unsetVariableFields( $message );
		$expected = array (
			'contribution_tracking_id' => $init['contribution_tracking_id'],
			'country' => 'US',
			'fee' => '0',
			'gateway' => 'paypal_ec',
			'gateway_txn_id' => '5EJ123456T987654S',
			'gateway_session_id' => 'EC-4V987654XA123456V',
			'language' => 'en',
			'order_id' => $init['contribution_tracking_id'] . '.0',
			'payment_method' => 'paypal',
			'payment_submethod' => '',
			'response' => false,
			'user_ip' => '127.0.0.1',
			'utm_source' => '..rpaypal',
			'city' => 'San Francisco',
			'currency' => 'USD',
			'email' => 'donor@generous.net',
			'first_name' => 'Fezziwig',
			'gross' => '1.55',
			'last_name' => 'Fowl',
			'recurring' => '1',
			'state_province' => 'CA',
			'street_address' => '123 Fake Street',
			'postal_code' => '94105',
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
			'subscr_id' => 'I-88J1M3DLSF0'
		);
		$this->assertEquals( $expected, $message );
		$this->assertNull(
			DonationQueue::instance()->pop( 'complete' ),
			'Sending extra messages to complete queue!'
		);
	}

	/**
	 * Check that we send the donor back to paypal to try a different source
	 */
	function testProcessDonorReturnPaymentRetry() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';

		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '10486' );
		$result = $gateway->processDonorReturn( array(
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		) );

		$message = DonationQueue::instance()->pop( 'complete' );
		$this->assertNull( $message, 'Should not queue a message' );
		$this->assertFalse( $result->isFailed() );
		$redirect = $result->getRedirect();
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-2D123456D9876543U',
			$redirect
		);
	}

	public function testProcessDonorReturnRecurringRetry() {
		$init = $this->getDonorTestData( 'US' );
		$init['contribution_tracking_id'] = '45931210';
		$init['recurring'] = '1';
		$this->setUpRequest( $init, array( 'Donor' => $init ) );
		$gateway = $this->getFreshGatewayObject( $init );
		$gateway->setDummyGatewayResponseCode( '10486' );
		$result = $gateway->processDonorReturn( array(
			'token' => 'EC%2d2D123456D9876543U',
			'PayerID' => 'ASDASD'
		) );

		$this->assertNull(
			DonationQueue::instance()->pop( 'complete' ),
			'Sending a spurious message to the complete queue!'
		);
		$this->assertFalse( $result->isFailed() );
		$redirect = $result->getRedirect();
		$this->assertEquals(
			'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-2D123456D9876543U',
			$redirect
		);
	}

}
