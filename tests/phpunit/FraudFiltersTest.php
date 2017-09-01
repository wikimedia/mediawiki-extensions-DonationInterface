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

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group FraudFilters
 */
class DonationInterface_FraudFiltersTest extends DonationInterfaceTestCase {

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject $requestFactory
	 */
	protected $requestFactory;

	/**
	 * @var PHPUnit_Framework_MockObject_MockObject $request
	 */
	protected $request;

	public function setUp() {
		parent::setUp();
		$this->requestFactory = $this->getMockBuilder(
			'\MaxMind\WebService\Http\RequestFactory'
		)->disableOriginalConstructor()->getMock();

		$this->request = $this->getMockBuilder(
			'MaxMind\WebService\Http\CurlRequest'
		)->disableOriginalConstructor()->getMock();

		$this->requestFactory->method( 'request' )->willReturn(
			$this->request
		);

		$this->setMwGlobals( array(
			'wgDonationInterfaceEnableMinFraud' => true,
			'wgDonationInterfaceMinFraudErrorScore' => 50,
			'wgDonationInterfaceMinFraudClientOptions' => array(
				'host' => '0.0.0.0',
				'httpRequestFactory' => $this->requestFactory
			),
		) );
	}

	/**
	 * When minFraud gets a blank answer, we should assign points according to
	 * $wgDonationInterfaceMinFraudErrorScore.
	 */
	function testMinFraudErrorScore() {
		$this->request->method( 'post' )->willReturn( '' );
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudFilters();

		$this->assertEquals( 'reject', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 107.5, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = array(
			'validation_action' => 'reject',
			'risk_score' => 107.5,
			'score_breakdown' => array(
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 37.5,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'minfraud_filter' => 50,
			),
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		);
		$this->assertEquals( $expected, $message );
	}

	/**
	 * When minFraud gets a blank answer, we should assign points according to
	 * $wgDonationInterfaceMinFraudErrorScore.
	 */
	function testMinFraudRealScore() {
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';

		$gateway = $this->getFreshGatewayObject( $options );

		$this->request->expects( $this->once() )
			->method( 'post' )
			->with(
				'{"billing":{"city":"San Francisco","region":"CA","postal":"94105","country":"US"},' .
				'"device":{"ip_address":"127.0.0.1"},' .
				'"email":{"address":"daf162af7e894faf3d55a18ec7bfa795","domain":"wikipedia.org"},' .
				'"event":{"transaction_id":"' .
				$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ) .'"}}'
			)->willReturn( [
			200, 'application/json', '{
	"id": "5bc5d6c2-b2c8-40af-87f4-6d61af86b6ae",
	"risk_score": 15.25,
	"funds_remaining": 250.00,
	"queries_remaining": 500000,
 
	"ip_address": {
		"risk": 15.25
	},
 
	"disposition": {
		 "action": "accept",
		 "reason": "default"
	},

	"warnings": []
	 }'
		] );

		$gateway->runAntifraudFilters();

		$this->assertEquals( 'challenge', $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 72.75, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = array(
			'validation_action' => 'challenge',
			'risk_score' => 72.75,
			'score_breakdown' => array(
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 37.5,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'minfraud_filter' => 15.25,
			),
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'globalcollect',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		);
		$this->assertEquals( $expected, $message );
	}
}
// Stub out Minfraud class for CI tests
if ( !class_exists( 'CreditCardFraudDetection' ) ) {
    class CreditCardFraudDetection
    {
        public $server;

        public function filter_field($a, $b)
        {
            return 'blah';
        }

        public function query()
        {
        }

        public function input($a)
        {
        }

        public function output()
        {
            return array();
        }
    }
}
