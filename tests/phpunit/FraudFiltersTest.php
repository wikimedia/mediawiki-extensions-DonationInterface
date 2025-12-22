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

use MediaWiki\Extension\DonationInterface\Tests\MinFraudTestTrait;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\ValidationAction;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group FraudFilters
 * @covers \FraudFilter
 */
class FraudFiltersTest extends DonationInterfaceTestCase {
	use MinFraudTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpMinFraudMocks();

		$this->setMwGlobals( $this->getAllGlobalVariants( [
			'CustomFiltersActionRanges' => [
				ValidationAction::PROCESS => [ 0, 25 ],
				ValidationAction::REVIEW => [ 25, 50 ],
				ValidationAction::CHALLENGE => [ 50, 75 ],
				ValidationAction::REJECT => [ 75, 100 ],
			],
			'CustomFiltersFunctions' => [
				'getScoreCountryMap' => 50,
				'getScoreUtmCampaignMap' => 50,
				'getScoreUtmSourceMap' => 15,
				'getScoreUtmMediumMap' => 15,
				'getScoreEmailDomainMap' => 75,
				'getCVVResult' => 50,
				'getAVSResult' => 50,
			],
			'CountryMap' => [
				'US' => 40,
				'CA' => 15,
				'RU' => -4,
			],
			'UtmCampaignMap' => [
				'/^(C14_)/' => 14,
				'/^(spontaneous)/' => 5
			]
		] + $this->getMinFraudGlobalsWithoutPrefix() ) );
	}

	/**
	 * When minFraud gets a blank answer, we should assign points according to
	 * $wgDonationInterfaceMinFraudErrorScore.
	 */
	public function testMinFraudErrorScore() {
		$this->minFraudRequest->method( 'post' )->willReturn( [] );
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';
		$request = RequestContext::getMain()->getRequest();
		$request->setHeaders( [
			'user-agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
			'accept-language' => 'tlh-QR q=0.9'
		] );

		$gateway = $this->getFreshGatewayObject( $options );

		$gateway->runAntifraudFilters();

		$this->assertEquals( ValidationAction::REJECT, $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 107.5, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = [
			'validation_action' => ValidationAction::REJECT,
			'risk_score' => 107.5,
			'score_breakdown' => [
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 37.5,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'minfraud_filter' => 50,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'ingenico',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];
		$this->assertEquals( $expected, $message );
	}

	/**
	 * Test we correctly add a real score from minFraud
	 */
	public function testMinFraudRealScore() {
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';
		$request = RequestContext::getMain()->getRequest();
		$request->setHeaders( [
			'user-agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
			'accept-language' => 'tlh-QR q=0.9'
		] );
		$this->overrideConfigValues( [
			'DonationInterfaceMinFraudExtraFields' => [],
		] );

		$gateway = $this->getFreshGatewayObject( $options );

		$this->minFraudRequest->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( function ( $postData ) use ( $gateway ) {
				$decoded = json_decode( $postData, true );
				$expected = [
					'billing' => [
						'city' => 'San Francisco',
						'region' => 'CA',
						'postal' => '94105',
						'country' => 'US',
					],
					'device' => [
						'ip_address' => '127.0.0.1',
						'user_agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
						'accept_language' => 'tlh-QR q=0.9',
					],
					'email' => [
						'address' => 'daf162af7e894faf3d55a18ec7bfa795',
						'domain' => 'wikipedia.org',
					],
					'event' => [
						'transaction_id' => (string)$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
					],
				];
				$this->assertArraySubmapSame( $expected, $decoded );
				return true;
			} )
			)->willReturn( [
				200, 'application/json', file_get_contents(
					__DIR__ . '/includes/Responses/minFraud/15points.json'
				)
			] );

		$gateway->runAntifraudFilters();

		$this->assertEquals( ValidationAction::CHALLENGE, $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 72.75, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = [
			'validation_action' => ValidationAction::CHALLENGE,
			'risk_score' => 72.75,
			'score_breakdown' => [
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 37.5,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'minfraud_filter' => 15.25,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => false,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'ingenico',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];
		$this->assertEquals( $expected, $message );
	}

	/**
	 * Make sure we send the right stuff when extra fields are enabled
	 */
	public function testMinFraudExtras() {
		$this->overrideConfigValues( [
			'DonationInterfaceMinFraudExtraFields' => [
				'email',
				'first_name',
				'last_name',
				'street_address',
				'amount',
				'currency',
			],
		] );
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';
		$request = RequestContext::getMain()->getRequest();
		$request->setHeaders( [
			'user-agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
			'accept-language' => 'tlh-QR q=0.9'
		] );

		$gateway = $this->getFreshGatewayObject( $options );

		$this->minFraudRequest->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( function ( $postData ) use ( $gateway ) {
				$decoded = json_decode( $postData, true );
				$expected = [
					'billing' => [
						'city' => 'San Francisco',
						'region' => 'CA',
						'postal' => '94105',
						'country' => 'US',
						'first_name' => 'Firstname',
						'last_name' => 'Surname',
						'address' => '123 Fake Street',
					],
					'device' => [
						'ip_address' => '127.0.0.1',
						'user_agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
						'accept_language' => 'tlh-QR q=0.9',
					],
					'email' => [
						'address' => 'somebody@wikipedia.org',
						'domain' => 'wikipedia.org',
					],
					'event' => [
						'transaction_id' => (string)$gateway->getData_Unstaged_Escaped(
							'contribution_tracking_id'
						),
					],
					'order' => [
						'amount' => '4.55',
						'currency' => 'USD',
					],
				];
				$this->assertArraySubmapSame( $expected, $decoded );
				return true;
			} )
			)->willReturn( $this->getMinFraudMockResponse() );

		$gateway->runAntifraudFilters();

		$this->assertEquals( ValidationAction::CHALLENGE, $gateway->getValidationAction(), 'Validation action is not as expected' );
		$exposed = TestingAccessWrapper::newFromObject( $gateway );
		$this->assertEquals( 72.75, $exposed->risk_score, 'RiskScore is not as expected for failure mode' );
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );
		$expected = [
			'validation_action' => ValidationAction::CHALLENGE,
			'risk_score' => 72.75,
			'score_breakdown' => [
				'initial' => 0,
				'getScoreUtmCampaignMap' => 0,
				'getScoreCountryMap' => 20,
				'getScoreUtmSourceMap' => 0,
				'getScoreUtmMediumMap' => 0,
				'getScoreEmailDomainMap' => 37.5,
				'getCVVResult' => 0,
				'getAVSResult' => 0,
				'minfraud_filter' => 15.25,
			],
			'user_ip' => '127.0.0.1',
			'gateway_txn_id' => null,
			'date' => $message['date'],
			'server' => gethostname(),
			'gateway' => 'ingenico',
			'contribution_tracking_id' => $gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ),
			'order_id' => $gateway->getData_Unstaged_Escaped( 'order_id' ),
			'payment_method' => 'cc',
		];
		$this->assertEquals( $expected, $message );
	}

	/**
	 * Make sure we send the right stuff when extra fields are enabled and
	 * we're not collecting address fields.
	 */
	public function testMinFraudExtrasNoAddress() {
		$options = $this->getDonorTestData( 'BR' );
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';
		$request = RequestContext::getMain()->getRequest();
		$request->setHeaders( [
			'user-agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
			'accept-language' => 'tlh-QR q=0.9'
		] );

		$gateway = $this->getFreshGatewayObject( $options );

		$this->overrideConfigValues( [
			'DonationInterfaceMinFraudExtraFields' => [
				'email',
				'first_name',
				'last_name',
				'street_address',
				'amount',
				'currency',
			],
		] );
		$this->minFraudRequest->expects( $this->once() )
			->method( 'post' )
			->with(
				'{"billing":{"country":"BR","first_name":"Nome","last_name":"Apelido"},' .
				'"device":{"ip_address":"127.0.0.1","user_agent":' .
				'"NCSA_Mosaic\/2.0 (Solaris 2.4)","accept_language":"tlh-QR q=0.9"},' .
				'"email":{"address":"somebody@wikipedia.org","domain":' .
				'"wikipedia.org"},"event":{"transaction_id":"' .
				$gateway->getData_Unstaged_Escaped( 'contribution_tracking_id' ) .
				'"},"order":{"amount":"100.00","currency":"BRL"}}'
			)->willReturn( $this->getMinFraudMockResponse() );

		$gateway->runAntifraudFilters();
	}

	/**
	 * Don't send 'region' parameter when it's not an ISO code
	 * @covers Gateway_Extras_CustomFilters_MinFraud::getBillingParams
	 */
	public function testMinFraudExtrasNonIsoRegion() {
		$options = $this->getDonorTestData();
		$options['email'] = 'somebody@wikipedia.org';
		$options['payment_method'] = 'cc';
		$options['state_province'] = 'California';
		$request = RequestContext::getMain()->getRequest();
		$request->setHeaders( [
			'user-agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
			'accept-language' => 'tlh-QR q=0.9'
		] );

		$gateway = $this->getFreshGatewayObject( $options );

		$this->overrideConfigValues( [
			'DonationInterfaceMinFraudExtraFields' => [
				'email',
				'first_name',
				'last_name',
				'street_address',
				'amount',
				'currency',
			],
		] );
		$this->minFraudRequest->expects( $this->once() )
			->method( 'post' )
			->with( $this->callback( function ( $postData ) use ( $gateway ) {
				$decoded = json_decode( $postData, true );
				$expected = [
					'billing' => [
						'city' => 'San Francisco',
						'postal' => '94105',
						'country' => 'US',
						'first_name' => 'Firstname',
						'last_name' => 'Surname',
						'address' => '123 Fake Street',
					],
					'device' => [
						'ip_address' => '127.0.0.1',
						'user_agent' => 'NCSA_Mosaic/2.0 (Solaris 2.4)',
						'accept_language' => 'tlh-QR q=0.9',
					],
					'email' => [
						'address' => 'somebody@wikipedia.org',
						'domain' => 'wikipedia.org',
					],
					'event' => [
						'transaction_id' => (string)$gateway->getData_Unstaged_Escaped(
							'contribution_tracking_id'
						),
					],
					'order' => [
						'amount' => '4.55',
						'currency' => 'USD',
					],
				];
				$this->assertArraySubmapSame( $expected, $decoded );
				$this->assertArrayNotHasKey( 'region', $expected['billing'] );
				return true;
			} )
			)->willReturn( $this->getMinFraudMockResponse() );

		$gateway->runAntifraudFilters();
	}

	/**
	 * Tests whether a transaction that exactly matches a predefined pattern filter is
	 * rejected with the expected risk score and validation action.
	 *
	 * The method sets up a pattern filter configuration, creates a test transaction
	 * with matching data, and validates the filter behavior through assertions on
	 * the validation action, risk score, and antifraud queue message.
	 *
	 * @return void
	 */
	public function testPatternFilterExactMatch(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_actor' => [
						'utm_key' => 'bad_utm_key',
						'utm_medium' => 'bad_utm_medium',
						'utm_campaign' => 'bad_utm_campaign',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['utm_key'] = 'bad_utm_key';
		$testGatewayData['utm_medium'] = 'bad_utm_medium';
		$testGatewayData['utm_campaign'] = 'bad_utm_campaign';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we reject the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::REJECT,
			$testGatewayInstance->getValidationAction(),
			'Should reject transaction matching pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertEquals(
			100,
			$exposed->risk_score,
			'Risk Score should be 100 from the pattern filter match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::REJECT, $message['validation_action'] );
		$this->assertEquals( 100, $message['risk_score'] );
		$this->assertArrayHasKey( 'PatternFilter_test_ruleset_bad_actor', $message['score_breakdown'] );
		$this->assertEquals( 100, $message['score_breakdown']['PatternFilter_test_ruleset_bad_actor'] );
	}

	public function testPatternFilterBasicWildcardMatch(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_wildcard_actor' => [
						// simple wildcard matching substring
						'email' => '*@bad-domain.com',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['email'] = 'bob@bad-domain.com';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we reject the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::REJECT,
			$testGatewayInstance->getValidationAction(),
			'Should reject transaction matching pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertEquals(
			100,
			$exposed->risk_score,
			'Risk Score should be 100 from the pattern wildcard match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::REJECT, $message['validation_action'] );
		$this->assertEquals( 100, $message['risk_score'] );
		$this->assertArrayHasKey( 'PatternFilter_test_ruleset_bad_wildcard_actor', $message['score_breakdown'] );
		$this->assertEquals( 100, $message['score_breakdown']['PatternFilter_test_ruleset_bad_wildcard_actor'] );
	}

	public function testPatternFilterAdvancedWildcardMatchInMiddleOfString(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_wildcard_actor' => [
						// here the wildcard is in the middle of the string
						'email' => 'bob@*-domain.com',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['email'] = 'bob@bad-domain.com';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we reject the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::REJECT,
			$testGatewayInstance->getValidationAction(),
			'Should reject transaction matching pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertEquals(
			100,
			$exposed->risk_score,
			'Risk Score should be 100 from the pattern wildcard match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::REJECT, $message['validation_action'] );
		$this->assertEquals( 100, $message['risk_score'] );
		$this->assertArrayHasKey( 'PatternFilter_test_ruleset_bad_wildcard_actor', $message['score_breakdown'] );
		$this->assertEquals( 100, $message['score_breakdown']['PatternFilter_test_ruleset_bad_wildcard_actor'] );
	}

	public function testPatternFilterAdvancedWildcardMultipleAsterisks(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_wildcard_actor' => [
						// here we use multiple wildcards in different locations
						'email' => 'bob@bad*.co.*',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['email'] = 'bob@bad-domain.co.jp';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we reject the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::REJECT,
			$testGatewayInstance->getValidationAction(),
			'Should reject transaction matching pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertEquals(
			100,
			$exposed->risk_score,
			'Risk Score should be 100 from the pattern wildcard match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::REJECT, $message['validation_action'] );
		$this->assertEquals( 100, $message['risk_score'] );
		$this->assertArrayHasKey( 'PatternFilter_test_ruleset_bad_wildcard_actor', $message['score_breakdown'] );
		$this->assertEquals( 100, $message['score_breakdown']['PatternFilter_test_ruleset_bad_wildcard_actor'] );
	}

	public function testPatternFilterRegexMatch(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_wildcard_actor' => [
						// here we use a regex to check for a 3 numeric character username in the email
						'email' => '/[1-9]{3}@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/i',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['email'] = '123@bad-domain.co.jp';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we reject the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::REJECT,
			$testGatewayInstance->getValidationAction(),
			'Should reject transaction matching pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertEquals(
			100,
			$exposed->risk_score,
			'Risk Score should be 100 from the pattern wildcard match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::REJECT, $message['validation_action'] );
		$this->assertEquals( 100, $message['risk_score'] );
		$this->assertArrayHasKey( 'PatternFilter_test_ruleset_bad_wildcard_actor', $message['score_breakdown'] );
		$this->assertEquals( 100, $message['score_breakdown']['PatternFilter_test_ruleset_bad_wildcard_actor'] );
	}

	public function testPatternFilterRegexNoMatch(): void {
		$this->setMwGlobals( static::getAllGlobalVariants( [
			// Add a test pattern to match against
			'PatternFilters' => [
				'PreAuthorize' => [
					'test_ruleset_bad_wildcard_actor' => [
						// here we use a regex to match 3 digit usernames with numeric values
						'email' => '/[1-9]{3}@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/i',
						'amount' => '25.00',
						'currency' => 'USD',
						'failScore' => 100,
					]
				]
			]
		] ) );

		$testGatewayData = static::getDonorTestData();
		$testGatewayData['email'] = 'abc@bad-domain.co.jp';
		$testGatewayData['amount'] = '25.00';
		$testGatewayData['currency'] = 'USD';
		$testGatewayData['payment_method'] = 'cc';
		$testGatewayInstance = $this->getFreshGatewayObject( $testGatewayData );

		// trigger the filters against the test gateway data
		Gateway_Extras_CustomFilters::onGatewayReady( $testGatewayInstance );

		// confirm we process the transaction and assign the expected risk score
		$this->assertEquals(
			ValidationAction::PROCESS,
			$testGatewayInstance->getValidationAction(),
			'Should process transaction that does not match pattern filter'
		);

		$exposed = TestingAccessWrapper::newFromObject( $testGatewayInstance );
		$this->assertSame(
			0,
			$exposed->risk_score,
			'Risk Score should be 0 from no match'
		);

		// check the antifraud queue message to make sure that matches
		$message = QueueWrapper::getQueue( 'payments-antifraud' )->pop();
		SourceFields::removeFromMessage( $message );

		$this->assertEquals( ValidationAction::PROCESS, $message['validation_action'] );
		$this->assertSame( 0, $message['risk_score'] );
	}
}
