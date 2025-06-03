<?php

/**
 * @group DonationInterface
 * @group DonorPortal
 * @covers \CiviproxyConnect
 */
class CiviproxyConnectTest extends MediaWikiIntegrationTestCase {

	use MockHttpTrait;

	public function testFetchDonorSummary(): void {
		$this->installMockHttp(
			'{"values":[{"id":211,"name":"Jimmy Wales","email":"jwales@example.com",' .
			'"address":{"street_address":"1 Montgomery Street","city":"San Francisco",' .
			'"state_province":"CA","postal_code":"94104","country":"US"},"hasMultipleContacts":false,' .
			'"contributions":[{"id":116,"contribution_recur_id":null,' .
			'"contribution_extra.original_currency":"USD","contribution_extra.original_amount":10,' .
			'"payment_instrument_id:name":"Credit Card: Visa"}],' .
			'"recurringContributions":[{"id":4,"amount":2,"currency":"USD","frequency_unit":"month",' .
			'"next_sched_contribution_date":"2025-06-05 14:07:54",' .
			'"payment_instrument_id:name":"Credit Card: Visa"}]}],"entity":"WMFContact",' .
			'"action":"getDonorSummary","debug":null,"version":4,"count":1,"countFetched":1}'
		);
		$response = CiviproxyConnect::getDonorSummary( '30dab568088149f83b25a252b8691438_1746454758_168', 211 );
		$this->assertEquals(
			[
				'id' => 211,
				'name' => 'Jimmy Wales',
				'email' => 'jwales@example.com',
				'address' => [
					'street_address' => '1 Montgomery Street',
					'city' => 'San Francisco',
					'state_province' => 'CA',
					'postal_code' => '94104',
					'country' => 'US',
				],
				'hasMultipleContacts' => false,
				'contributions' => [
					[
						'id' => 116,
						'contribution_recur_id' => null,
						'contribution_extra.original_currency' => 'USD',
						'contribution_extra.original_amount' => 10,
						'payment_instrument_id:name' => 'Credit Card: Visa',
					],
				],
				'recurringContributions' => [
					[
						'id' => 4,
						'amount' => 2,
						'currency' => 'USD',
						'frequency_unit' => 'month',
						'next_sched_contribution_date' => '2025-06-05 14:07:54',
						'payment_instrument_id:name' => 'Credit Card: Visa',
					],
				],
			],
			$response
		);
	}
}
