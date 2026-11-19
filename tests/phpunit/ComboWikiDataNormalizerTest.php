<?php

use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;
use MediaWiki\Extension\DonationInterface\ComboWiki\DataNormalizer;
use Psr\Log\NullLogger;

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
	 * utm_source is banner.landing_page.payment_method_family. It must be rebuilt
	 * every time, including when restored from session with no source (as in
	 * Hooks::updateContributionTracking on submit), so the selected payment
	 * method is filled in and the landing page is always 'combowiki'.
	 *
	 * @dataProvider provideUtmSourceCases
	 */
	public function testNormalizesUtmSource(
		array $data,
		array $sources,
		string $expectedUtmSource
	): void {
		$details = new DonationDetails();
		$details->setValue( 'user_ip', '127.0.0.1' );
		$details->setValue( 'country', 'US' );
		foreach ( $data as $key => $value ) {
			$details->setValue( $key, $value );
		}
		foreach ( $sources as $key => $source ) {
			$details->setSource( $key, $source );
		}

		$normalizer = $this->newNormalizer( 'US' );
		$normalizer->normalize( $details );
		$this->assertSame( $expectedUtmSource, $details->getValue( 'utm_source' ) );
		$this->assertSame( 'combowiki', $details->getValue( 'landing_page' ) );

		// Normalizing again (as happens on later requests) must not change the result
		$normalizer->normalize( $details );
		$this->assertSame( $expectedUtmSource, $details->getValue( 'utm_source' ) );
	}

	public static function provideUtmSourceCases(): array {
		return [
			// name => [ data, sources, expected utm_source ]
			'nothing set on page load' => [ [], [], '.combowiki.' ],
			'payment method from url, no utm_source' => [
				[ 'payment_method' => 'cc' ], [ 'payment_method' => 'get' ], '.combowiki.cc'
			],
			'banner utm_source from url, landing page and payment method replaced' => [
				[ 'utm_source' => 'B2526_banner.other_lp.paypal', 'payment_method' => 'cc' ],
				[ 'utm_source' => 'get', 'payment_method' => 'get' ],
				'B2526_banner.combowiki.cc'
			],
			'empty utm_source from session, payment method posted on submit' => [
				[ 'utm_source' => '..', 'payment_method' => 'paypal' ],
				[ 'payment_method' => 'post' ],
				'.combowiki.paypal'
			],
			'banner utm_source from session, payment method changed on submit' => [
				[ 'utm_source' => 'B2526_banner.combowiki.paypal', 'payment_method' => 'cc', 'recurring' => '1' ],
				[ 'payment_method' => 'post', 'recurring' => 'post' ],
				'B2526_banner.combowiki.rcc'
			],
		];
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
				parent::__construct( $config, new NullLogger() );
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
