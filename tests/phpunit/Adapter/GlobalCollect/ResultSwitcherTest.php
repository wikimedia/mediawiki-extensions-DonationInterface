<?php
/**
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 */
class DonationInterface_Adapter_GlobalCollect_ResultSwitcherTest extends DonationInterfaceTestCase {
	protected $testAdapterClass = 'TestingGlobalCollectAdapter';

	public function setUp() {
		parent::setUp();

		$this->setMwGlobals(
			array(
				'wgGlobalCollectGatewayEnabled' => true,
			)
		);
	}

	/**
	 * Assuming we've popped out of the frame, does processing succeed?
	 */
	public function testResultSwitcherLiberatedSuccess() {
		$donorTestData = $this->getDonorTestData( 'FR' );
		$donorTestData['payment_method'] = 'cc';
		$donorTestData['payment_submethod'] = 'visa';
		$donorTestData['email'] = 'innocent@localhost.net';
		$donorTestData['order_id'] = mt_rand();
		$session['Donor'] = $donorTestData;
		// Mark the order as already popped out of the iframe
		$session['order_status'][$donorTestData['order_id']] = 'liberated';
		$request = array(
			'REF' => $donorTestData['order_id'],
			'CVVRESULT' => 'M',
			'AVSRESULT' => '0',
			'language' => 'fr', // FIXME: verifyFormOutput conflates request with other stuff
		);
		$assertNodes = array(
			'headers' => array(
				'Location' => function ( $location ) use ( $donorTestData ) {
					// Do this after the real processing to avoid side effects
					$gateway = $this->getFreshGatewayObject( $donorTestData );
					$url = ResultPages::getThankYouPage( $gateway );
					$this->assertEquals( $url, $location );
				}
			)
		);

		$this->verifyFormOutput( 'GlobalCollectGatewayResult', $request, $assertNodes, false, $session );
		// Make sure we logged the expected cURL attempts
		$messages = self::getLogMatches( 'info', '/Preparing to send GET_ORDERSTATUS transaction to Global Collect/' );
		$this->assertNotEmpty( $messages );
		$messages = self::getLogMatches( 'info', '/Preparing to send SET_PAYMENT transaction to Global Collect/' );
		$this->assertNotEmpty( $messages );
	}
}
