<?php

use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataNormalizer;

/**
 * Tests how DataNormalizer decides the donor's country. In order, it uses:
 * a valid requested country (uppercased), a country already in the session,
 * the GeoIP country, and finally US.
 *
 * The GeoIP lookup is faked, because the MaxMind database it reads is not
 * available in CI or locally.
 *
 * @group Fundraising
 * @group DonationInterface
 * @group ComboWiki
 * @covers \MediaWiki\Extension\DonationInterface\ComboWiki\DataNormalizer
 */
class ComboWikiDataNormalizerTest extends MediaWikiIntegrationTestCase {
	/**
	 * Currency is checked alongside country because normalizeCurrency() runs
	 * straight after and derives it from the country, so a wrong country
	 * also shows up as a wrong currency.
	 *
	 * @dataProvider provideCountryCases
	 */
	public function testNormalizesCountry(
		?string $country,
		string $source,
		?string $geoIpCountry,
		string $expectedCountry,
		string $expectedCurrency
	): void {
		$details = $this->newDonationDetails( $country, $source );

		$this->newNormalizer( $geoIpCountry )->normalize( $details );

		$this->assertSame( $expectedCountry, $details->getValue( 'country' ) );
		$this->assertSame( $expectedCurrency, $details->getValue( 'currency' ) );
	}

	/**
	 * The source is set so cases can model a country from the URL ('get') or
	 * from an earlier page load ('session'). normalizeCountry() validates both.
	 *
	 * user_ip is set so normalizeIpCountry() reaches the GeoIP lookup.
	 */
	private function newDonationDetails( ?string $country, string $source ): DonationDetails {
		$details = new DonationDetails();
		$details->setValue( 'user_ip', '127.0.0.1' );
		if ( $country !== null ) {
			$details->setValue( 'country', $country );
			$details->setSource( 'country', $source );
		}

		return $details;
	}

	/**
	 * Returns a normalizer whose GeoIP lookup gives a fixed answer, with
	 * null meaning the lookup failed.
	 */
	private function newNormalizer( ?string $geoIpCountry ): DataNormalizer {
		return new class( $this->getServiceContainer()->getMainConfig(), $geoIpCountry ) extends DataNormalizer {
			public function __construct( $config, private ?string $geoIpCountry ) {
				parent::__construct( $config );
			}

			protected function lookUpIpCountry( string $ip ): ?string {
				return $this->geoIpCountry;
			}
		};
	}

	public static function provideCountryCases(): array {
		return [
			// name => [ country, source, GeoIP, expected country, expected currency ]

			// When country is null, source is unused.
			'URL beats GeoIP' => [ 'FR', 'get', 'GB', 'FR', 'EUR' ],

			// isValidIsoCode() is case-sensitive, so the uppercase form has to be
			// stored or currency lookup and gateway selection fail.
			'lowercase link is uppercased' => [ 'fr', 'get', null, 'FR', 'EUR' ],

			// XX is a placeholder for an unknown country, not a real ISO code,
			// so it must fall through to GeoIP.
			'placeholder XX falls back to GeoIP' => [ 'XX', 'get', 'GB', 'GB', 'GBP' ],
			'no country uses GeoIP' => [ null, 'get', 'GB', 'GB', 'GBP' ],

			// What happens on localhost, where GeoIP always fails.
			'no country and no GeoIP defaults to US' => [ null, 'get', null, 'US', 'USD' ],

			// A valid session country wins, so GeoIP is not consulted even though
			// it gives a different answer.
			'session country is kept' => [ 'DE', 'session', 'GB', 'DE', 'EUR' ],

			// Session values are validated too: sessions saved before countries were
			// uppercased can hold lowercase codes, and the legacy forms share the
			// Donor session and store XX when they cannot find a country.
			'lowercase session country is uppercased' => [ 'fr', 'session', null, 'FR', 'EUR' ],
			'placeholder XX in session falls back to GeoIP' => [ 'XX', 'session', 'GB', 'GB', 'GBP' ],
			'placeholder XX in session and no GeoIP defaults to US' => [ 'XX', 'session', null, 'US', 'USD' ],
		];
	}
}
