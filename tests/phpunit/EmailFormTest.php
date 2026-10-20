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

	/**
	 * Messages containing HTML must be rendered with {{{ l10n }}}, otherwise
	 * Mustache escapes the markup and it shows up as literal text.
	 */
	public function testHtmlMessagesAreNotEscapedInTemplates(): void {
		$baseDir = __DIR__ . '/../..';
		$extension = json_decode( file_get_contents( "$baseDir/extension.json" ), true );
		$htmlMessages = [];
		foreach ( $extension['MessagesDirs']['DonationInterface'] as $dir ) {
			foreach ( glob( "$baseDir/$dir/*.json" ) as $file ) {
				if ( basename( $file ) === 'qqq.json' ) {
					continue;
				}
				$messages = json_decode( file_get_contents( $file ), true );
				foreach ( $messages as $key => $text ) {
					if ( is_string( $text ) && preg_match( '/<[a-z\/][^>]*>/i', $text ) ) {
						$htmlMessages[$key][] = basename( $file, '.json' );
					}
				}
			}
		}

		$problems = [];
		foreach ( glob( "$baseDir/email_forms/templates/*.mustache" ) as $template ) {
			preg_match_all( '/(?<!\{)\{\{\s*l10n\s+"([^"]+)"/', file_get_contents( $template ), $matches );
			foreach ( $matches[1] as $key ) {
				if ( isset( $htmlMessages[$key] ) ) {
					$problems[] = basename( $template ) . ": $key (" . implode( ', ', $htmlMessages[$key] ) . ')';
				}
			}
		}
		$this->assertSame( [], $problems,
			'These messages or translations contain HTML, which {{ l10n }} escapes. ' .
				'Remove the HTML or use {{{ l10n }}}.' );
	}
}
