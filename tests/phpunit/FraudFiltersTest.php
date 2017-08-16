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
 * @group FormChooser
 */
class DonationInterface_FraudFiltersTest extends DonationInterfaceTestCase {

	function testGCFraudFilters() {
		$this->setMwGlobals( array(
			'wgGlobalCollectGatewayEnableMinfraud' => true,
			'wgDonationInterfaceMinFraudServers' => array( '0.0.0.0' ),
		) );

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
}
// Stub out Minfraud class for CI tests
if ( !class_exists( 'CreditCardFraudDetection' ) ) {
	class CreditCardFraudDetection {
		public $server;
		public function filter_field( $a, $b ) {
			return 'blah';
		}
		public function query() { }
		public function input( $a ) { }
		public function output() {
			return array();
		}
	}
}
