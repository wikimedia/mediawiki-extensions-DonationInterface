<?php
/**
 * @group Fundraising
 * @group DonationInterface
 * @group Ingenico
 */
class DonationInterface_Adapter_Ingenico_ResultSwitcherTest extends BaseIngenicoTestCase {

	/**
	 * Assuming we've popped out of the frame, does processing succeed?
	 */
	public function testResultSwitcherLiberatedSuccess() {
		$this->markTestSkipped( 'ResultSwitcher not implemented' );
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

		$this->verifyFormOutput( 'IngenicoGatewayResult', $request, $assertNodes, false, $session );
		// Make sure we logged the expected cURL attempts
		$messages = $this->getLogMatches( 'info', '/Preparing to send GET_ORDERSTATUS transaction to Global Collect/' );
		$this->assertNotEmpty( $messages );
		$messages = $this->getLogMatches( 'info', '/Preparing to send SET_PAYMENT transaction to Global Collect/' );
		$this->assertNotEmpty( $messages );
	}
}
