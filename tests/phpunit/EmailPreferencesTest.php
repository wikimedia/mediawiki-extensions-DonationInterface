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

/**
 * @group DonationInterface
 * @group EmailPreferences
 */
class EmailPreferencesTest extends DonationInterfaceTestCase {

	/**
	 * Check that the opt-in queue message is what is expected
	 * @dataProvider expectedQueueNameDataProvider
	 * @param string $queueName
	 * @param string $url
	 * @param array $expected
	 * @return void
	 * @throws \PHPQueue\Exception\JobNotFoundException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 * @throws \SmashPig\Core\DataStores\DataStoreException
	 */
	public function testEmailPrefCenterQueue( string $queueName, string $url, array $expected ) {
		$params = [
			'title' => 'Special:EmailPreferences/' . $url,
			'email' => 'test@test.com',
			'contact_id' => '1',
			'checksum' => 'df3rf',
			'utm_source' => 'source',
			'utm_medium' => 'medium',
			'utm_campaign' => 'campaign',
			'country' => 'US',
			'language' => 'en',
			'send_email' => true,
		];

		$test = new EmailPreferences;
		$message = $test->setupQueueParams( $params, $queueName );

		QueueWrapper::push( $queueName, $message );

		$actual = QueueWrapper::getQueue( $queueName )->pop();
		SourceFields::removeFromMessage( $actual );

		$this->assertEquals( $expected, $actual, 'Message on the queue does not match' );

		$empty = QueueWrapper::getQueue( $queueName )->pop();
		$this->assertNull( $empty, 'Too many messages on the queue' );
	}

	public static function expectedQueueNameDataProvider() {
		return [
			[ 'email-preferences', 'emailPreferences', [
				'email' => 'test@test.com',
				'contact_id' => '1',
				'checksum' => 'df3rf',
				'country' => 'US',
				'language' => 'en',
				'send_email' => true,
			] ],
			[ 'opt-in', 'optin', [
				'email' => 'test@test.com',
				'contact_id' => '1',
				'checksum' => 'df3rf',
				'utm_source' => 'source',
				'utm_medium' => 'medium',
				'utm_campaign' => 'campaign',
			] ],
			[ 'unsubscribe', 'unsubscribe', [
				'email' => 'test@test.com',
			] ]
		];
	}
}
