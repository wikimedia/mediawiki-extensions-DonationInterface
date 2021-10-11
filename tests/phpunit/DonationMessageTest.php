<?php

/**
 * @group DonationInterface
 * @group Messages
 */
class DonationMessageTest extends MediaWikiIntegrationTestCase {

	public function testGetCountrySpecificMessage() {
		$actual = MessageUtils::getCountrySpecificMessage(
			'donate_interface-donor-fiscal_number',
			'BR',
			'pt'
		);
		$expected = wfMessage( 'donate_interface-donor-fiscal_number-br' )
						->inLanguage( 'pt' )
						->text();
		$this->assertEquals( $expected, $actual, 'Not using the country specific message' );
	}
}
