<?php

/**
 * @group Fundraising
 * @group DonationInterface
 * @group GlobalCollect
 * @group GlobalCollectApi
 * @group medium
 */

class GlobalCollectApiTest extends ApiTestCase {

	public function testGoodSubmit() {
		$init = DonationInterfaceTestCase::getDonorTestData();
		$init['email'] = 'good@innocent.com';
		$init['payment_method'] = 'cc';
		$init['gateway'] = 'globalcollect';
		$init['action'] = 'donate';

		$apiResult = $this->doApiRequest( $init );
		$result = $apiResult[0]['result'];
		$orderId = $result['orderid'];

		$this->assertEquals( 'url_placeholder', $result['formaction'], 'GC API not setting formaction' );
		$this->assertTrue( is_numeric( $orderId ), 'GC API not setting numeric order ID' );
		$this->assertTrue( $result['status'], 'GC API result status should be true' );
		preg_match( "/Special:GlobalCollectGatewayResult\?order_id={$orderId}\$/", $result['returnurl'], $match );
		$this->assertNotEmpty( $match, 'GC API not setting proper return url' );
	}
}
