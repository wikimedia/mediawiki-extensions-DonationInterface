<?php

/**
 * @group DonationInterface
 * @covers \EmailForm
 */
class EmailFormTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUserLang( 'en' );
	}

	/**
	 * @dataProvider provideL10n
	 */
	public function testL10n( string $key, array $params, string $expected ): void {
		$this->assertSame( $expected, EmailForm::l10n( $key, ...$params ) );
	}

	public static function provideL10n(): array {
		return [
			'HTML in a param is escaped' => [
				'donate_interface-email-primary-confirm',
				[ '"<svg/onload=alert(1)>"@example.org' ],
				'You have successfully confirmed your email address as ' .
					'&quot;&lt;svg/onload=alert(1)&gt;&quot;@example.org.',
			],
			'wikitext in a param is not expanded' => [
				'donate_interface-email-optin-fail',
				[ 'help@example.org', 'https://example.org/?x={{int:donate_interface-email-optin-fail|P}}' ],
				'There was an error processing your request. Please contact ' .
					'<a href="mailto:help@example.org">help@example.org</a> or ' .
					'<a href="https://example.org/?x={{int:donate_interface-email-optin-fail|P}}">' .
					'click here to try again</a>.',
			],
			'numbers still select the plural form' => [
				'emailpreferences-snooze-email',
				[ 30 ],
				'Pause emails for 30 days.',
			],
		];
	}
}
