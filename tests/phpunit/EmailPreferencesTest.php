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
	 */
	public function testSendToOptInQueue() {
		$queueName = 'opt-in';
		$expected = [
			'email' => 'test@test.com',
			'contact_id' => '1',
			'contact_hash' => 'df3rf',
			'utm_source' => 'source',
			'utm_medium' => 'medium',
			'utm_campaign' => 'campaign',
		];

		$params = [
			'title' => 'Special:EmailPreferences/optin',
			'email' => 'test@test.com',
			'contact_id' => '1',
			'contact_hash' => 'df3rf',
			'utm_source' => 'source',
			'utm_medium' => 'medium',
			'utm_campaign' => 'campaign'
		];

		$test = new EmailPreferences;
		$message = $test->setUpOptIn( $params );

		QueueWrapper::push( 'opt-in', $message );

		$actual = QueueWrapper::getQueue( $queueName )->pop();
		SourceFields::removeFromMessage( $actual );

		$this->assertEquals( $expected, $actual, 'Message on the queue does not match' );

		$empty = QueueWrapper::getQueue( $queueName )->pop();
		$this->assertNull( $empty, 'Too many messages on the queue' );
	}
}
