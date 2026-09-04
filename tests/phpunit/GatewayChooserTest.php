<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Special\GatewayRouter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Parser;

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

/**
 * @group Fundraising
 * @group DonationInterface
 * @group GatewayChooser
 * @group Database
 * @covers \GatewayChooser
 */
class GatewayChooserTest extends DonationInterfaceTestCase {

	/**
	 * @var string
	 */
	protected $dir;
	/**
	 * @var string
	 */
	protected $gatewayConfigGlobPattern;

	/**
	 * @param string|null $name The name of the test case
	 * @param array $data Any parameters read from a dataProvider
	 * @param string|int $dataName The name or index of the data set
	 */
	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		$adapterclass = TESTS_ADAPTER_DEFAULT;
		$this->testAdapterClass = $adapterclass;
		parent::__construct( $name, $data, $dataName );
	}

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'DonationInterfaceEnableGatewayChooser' => true,
			'IngenicoGatewayEnabled' => true,
			'DlocalGatewayEnabled' => true,
			'BraintreeGatewayEnabled' => true,
			'PaypalExpressGatewayEnabled' => true,
			'AdyenCheckoutGatewayEnabled' => true,
			'AmazonGatewayEnabled' => true,
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'ingenico' => 'IngenicoAdapter',
				'amazon' => 'AmazonAdapter',
				'adyen' => 'AdyenCheckoutAdapter',
				'paypal_ec' => 'PaypalExpressAdapter',
				'braintree' => 'BraintreeAdapter',
				'dlocal' => 'DlocalAdapter',
				'gravy' => 'GravyAdapter',
			],
		] );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->dir = $config->get( 'ExtensionDirectory' ) . DIRECTORY_SEPARATOR . 'DonationInterface' . DIRECTORY_SEPARATOR;
		$this->gatewayConfigGlobPattern = $this->dir . '*' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
	}

	public function testMaintenanceMode_Redirect() {
		$this->overrideConfigValues( [
			'DonationInterfaceFundraiserMaintenance' => true,
		] );

		$expectedLocation = Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL( '', false, PROTO_CURRENT );
		$assertNodes = [
			'headers' => [
				'Location' => $expectedLocation
			],
		];
		$initial = [
			'language' => 'en'
		];
		$this->verifyFormOutput( 'GatewayChooser', $initial, $assertNodes, false );
	}

	/**
	 * @dataProvider expectedInvalidGatewayDataProvider
	 * @param array $params
	 * @param bool $redirectToDonateWiki
	 *
	 * @return void
	 */
	public function testNoNeedParamGateWayChooser( array $params, bool $redirectToDonateWiki ) {
		$this->overrideConfigValue( 'DonationInterfaceNewDonationURL', 'https://test.example' );
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$context->setRequest( new FauxRequest( $params, false ) );
		$context->setOutput( $newOutput );
		$context->setTitle( $newTitle );

		$fc = new GatewayChooser();
		$fc->execute( null );
		$fc->getOutput()->output( true );
		$url = $fc->getRequest()->response()->getheader( 'Location' );
		$redirectMediaWikiUrl = 'https://test.example';
		if ( count( $params ) > 0 ) {
			$redirectMediaWikiUrl .= '?' . http_build_query( $params );
		}
		if ( $redirectToDonateWiki ) {
			$this->assertEquals( $redirectMediaWikiUrl, $url, 'invalid params redirect to donate wiki' );
		} else {
			$this->assertNotEquals( $redirectMediaWikiUrl, $url, 'valid params also redirect to donate wiki' );
		}
	}

	/**
	 * @dataProvider expectedGatewayDataProvider
	 * @param array $params Query-string parameters provided to GatewayChooser
	 * @param string|null $expectedSpecialGateway When a string, expect a redirect to the indicated special page.
	 *  When null, expect no redirect.
	 */
	public function testAssertExpectedGateway( array $params, ?string $expectedSpecialGateway ) {
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$context->setRequest( new FauxRequest( $params, false ) );
		$context->setOutput( $newOutput );
		$context->setTitle( $newTitle );

		$fc = new GatewayChooser();
		$fc->execute( null );
		$fc->getOutput()->output( true );
		$url = $fc->getRequest()->response()->getheader( 'Location' );

		if ( $expectedSpecialGateway === null ) {
			$this->assertNull( $url );
			return;
		}

		if ( !$url ) {
			$this->fail( 'No gateway returned for this configuration.' );
		}

		$parts = parse_url( $url );
		parse_str( $parts['query'], $query );
		$gateway = str_replace( 'Special:', '', $query['title'] );

		$this->assertEquals( $expectedSpecialGateway, $gateway, 'Gateway not match' );
		$this->assertArrayContains( $params, $query, 'Should pass through params to querystring' );
	}

	public static function expectedInvalidGatewayDataProvider() {
		// redirect to donate wiki if no payment_method or currency provided
		return [
			[ [ 'country' => 'US', 'currency' => 'USD' ], true ],
			[ [ 'payment_method' => 'cc', 'currency' => 'USD' ], true ],
			[ [ 'currency' => 'USD' ], true ],
			[ [ 'payment_method' => 'cc', 'country' => 'US', 'currency' => 'USD' ], false ],
		];
	}

	public static function expectedGatewayDataProvider() {
		// Gateways:
		// DlocalGateway
		// AmazonGateway
		// AdyenCheckoutGateway
		// PaypalExpressGateway
		// IngenicoGateway
		// A null value as the expected gateway means that no redirect is expected

		// TODO Add test cases for Google Pay

		return [
			// paypal payment method should be routed to GravyGateway
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'paypal', 'country' => 'CN', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'paypal', 'country' => 'GH', 'currency' => 'GHS' ], 'GravyGateway' ],
			// amazon payment method is only provided by AmazonGateway
			[ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD' ], 'AmazonGateway' ],

			// Ensure Gravy and amazon gateways are selected even if the currency is unsupported.
			// For both, for these test cases to work, CLP currency must not be included in
			// currencies.yaml, and in general.yaml, gateway_chooser/still_include_if_currency_is_not_supported
			// must be true.
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'CLP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'CLP' ], 'AmazonGateway' ],

			// bank transfer methods, all currently processed via DLocal
			[ [ 'payment_method' => 'bt', 'country' => 'AR', 'currency' => 'ARS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'BR', 'currency' => 'BRL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'CO', 'currency' => 'COP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'CL', 'currency' => 'CLP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'IN', 'currency' => 'INR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'PE', 'currency' => 'PEN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'ZA', 'currency' => 'ZAR' ], 'GravyGateway' ],

			// except CZ
			[ [ 'payment_method' => 'bt', 'country' => 'CZ', 'currency' => 'CZK' ], 'GravyGateway' ],

			// cash methods, all currently processed via DLocal
			[ [ 'payment_method' => 'cash', 'country' => 'AR', 'currency' => 'ARS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'BR', 'currency' => 'BRL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'CO', 'currency' => 'COP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'MX', 'currency' => 'INR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'PE', 'currency' => 'PEN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'UY', 'currency' => 'ZAR' ], 'GravyGateway' ],

			// iDEAL (NL-only realtime bank transfer)
			[ [ 'payment_method' => 'rtbt', 'country' => 'NL', 'currency' => 'EUR' ], 'GravyGateway' ],
			// Should work with submethod specified
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal', 'country' => 'NL', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'sepadirectdebit', 'country' => 'DE', 'currency' => 'EUR' ], 'GravyGateway' ],
			// Test country restriction on submethod
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal', 'country' => 'FR', 'currency' => 'EUR' ], null ],
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'sepadirectdebit', 'country' => 'FR', 'currency' => 'EUR' ], 'GravyGateway' ],
			// Test recurring specified only at the method level
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal', 'country' => 'NL', 'currency' => 'EUR', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'sepadirectdebit', 'country' => 'DE', 'currency' => 'EUR', 'recurring' => '1' ], 'GravyGateway' ],

			// Recurring: only paypal, ingenico and adyen got recurring
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR', 'recurring' => '1' ], 'GravyGateway' ], // adyen recurring
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY', 'recurring' => '1' ], 'GravyGateway' ], // ingenico recurring
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'GravyGateway' ], // paypal recurring
			// Amazon recurring is not finished; see T107391
			// [ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'AmazonGateway' ],

			// LATAM recurring should use dlocal
			[ [ 'payment_method' => 'cc', 'country' => 'AR', 'currency' => 'ARS', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BR', 'currency' => 'BRL', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CL', 'currency' => 'CLP', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CO', 'currency' => 'COP', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MX', 'currency' => 'MXN', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PE', 'currency' => 'PEN', 'recurring' => '1' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UY', 'currency' => 'UYU', 'recurring' => '1' ], 'GravyGateway' ],

			// below cc payment_method test cases for countries by national currency; originally generated by
			// `php extensions/DonationInterface/maintenance/TestCaseMaintenance.php`
			[ [ 'payment_method' => 'cc', 'country' => 'AD', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AE', 'currency' => 'AED' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AG', 'currency' => 'XCD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AL', 'currency' => 'ALL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AR', 'currency' => 'ARS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AS', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AU', 'currency' => 'AUD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BB', 'currency' => 'BBD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BD', 'currency' => 'BDT' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BE', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BG', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BH', 'currency' => 'BHD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BM', 'currency' => 'BMD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BO', 'currency' => 'BOB' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BR', 'currency' => 'BRL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BZ', 'currency' => 'BZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CA', 'currency' => 'CAD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CH', 'currency' => 'CHF' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CK', 'currency' => 'NZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CL', 'currency' => 'CLP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CN', 'currency' => 'CNY' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CO', 'currency' => 'COP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CR', 'currency' => 'CRC' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CY', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CZ', 'currency' => 'CZK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DE', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DK', 'currency' => 'DKK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DM', 'currency' => 'XCD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DO', 'currency' => 'DOP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DZ', 'currency' => 'DZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EC', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EE', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EG', 'currency' => 'EGP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ES', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FI', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FJ', 'currency' => 'FJD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FO', 'currency' => 'DKK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GB', 'currency' => 'GBP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GD', 'currency' => 'XCD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GF', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GL', 'currency' => 'DKK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GR', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GT', 'currency' => 'GTQ' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GU', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HK', 'currency' => 'HKD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HN', 'currency' => 'HNL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HR', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HR', 'currency' => 'HRK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HU', 'currency' => 'HUF' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ID', 'currency' => 'IDR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IE', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IL', 'currency' => 'ILS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IN', 'currency' => 'INR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JM', 'currency' => 'JMD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JO', 'currency' => 'JOD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KE', 'currency' => 'KES' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KI', 'currency' => 'AUD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KR', 'currency' => 'KRW' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KW', 'currency' => 'KWD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KZ', 'currency' => 'KZT' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LB', 'currency' => 'LBP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LI', 'currency' => 'CHF' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LK', 'currency' => 'LKR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LU', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LV', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MA', 'currency' => 'MAD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MH', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MK', 'currency' => 'MKD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MP', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MV', 'currency' => 'MVR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MX', 'currency' => 'MXN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MY', 'currency' => 'MYR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NI', 'currency' => 'NIO' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NL', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NO', 'currency' => 'NOK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NR', 'currency' => 'AUD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NZ', 'currency' => 'NZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'OM', 'currency' => 'OMR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PA', 'currency' => 'PAB' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PE', 'currency' => 'PEN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PH', 'currency' => 'PHP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PK', 'currency' => 'PKR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PL', 'currency' => 'PLN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PR', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PS', 'currency' => 'ILS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PW', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PY', 'currency' => 'PYG' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'QA', 'currency' => 'QAR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RE', 'currency' => 'NZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RO', 'currency' => 'RON' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SA', 'currency' => 'SAR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SC', 'currency' => 'SCR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SE', 'currency' => 'SEK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SK', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SG', 'currency' => 'SGD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TH', 'currency' => 'THB' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TN', 'currency' => 'TND' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TR', 'currency' => 'TRY' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TT', 'currency' => 'TTD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TW', 'currency' => 'TWD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UA', 'currency' => 'UAH' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'US', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UY', 'currency' => 'UYU' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VA', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VE', 'currency' => 'VEF' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VI', 'currency' => 'USD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VN', 'currency' => 'VND' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VU', 'currency' => 'VUV' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ZA', 'currency' => 'ZAR' ], 'GravyGateway' ],

			// apple pay (from web browser, not from mobile app)
			[ [ 'payment_method' => 'apple', 'country' => 'AU', 'currency' => 'AUD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'BR', 'currency' => 'BRL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'CA', 'currency' => 'CAD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'FR', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'GB', 'currency' => 'GBP' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IE', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IL', 'currency' => 'ILS' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IT', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'JP', 'currency' => 'JPY' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'MX', 'currency' => 'MXN' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'NL', 'currency' => 'EUR' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'NZ', 'currency' => 'NZD' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'SE', 'currency' => 'SEK' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'UA', 'currency' => 'UAH' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'US', 'currency' => 'USD' ], 'GravyGateway' ],

			// google pay (from web browser, not from mobile app)
			[ [ 'payment_method' => 'google', 'country' => 'BR', 'currency' => 'BRL' ], 'GravyGateway' ],
			[ [ 'payment_method' => 'google', 'country' => 'MX', 'currency' => 'MXN' ], 'GravyGateway' ],
		];
	}

	private function getExtensionConfig( $gateway ) {
		$yaml = new Parser();
		$configDir = $this->dir . $gateway . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "countries.yaml";
		$config = glob( $configDir );
		if ( $config ) {
			$content = $yaml->parse( file_get_contents( $config[0] ) );
			return $content;
		}
		return null;
	}

	public function testConfirmCountriesInCountryFieldsGatewayConfig() {
		// This test can fail if a country is present in the payment_submethods.yaml and
		// not in the countries.yaml
		$yaml = new Parser();
		$globPattern = $this->gatewayConfigGlobPattern . 'country_fields.yaml';

		foreach ( glob( $globPattern ) as $path ) {
			$gatewayDirArray = explode( DIRECTORY_SEPARATOR, $path );
			$gateway = $gatewayDirArray[count( $gatewayDirArray ) - 3];
			$gatewayDirConfig = $yaml->parse( file_get_contents( $path ) );
			$extensionConfig = $this->getExtensionConfig( $gateway );
			if ( $extensionConfig !== null ) {
				foreach ( $gatewayDirConfig as $key => $value ) {
					$this->assertContains( $key, $extensionConfig );
				}
			}
		}
	}

	public function testConfirmCountriesInPaymentSubmethodsGatewayConfig() {
		$yaml = new Parser();
		$globPattern = $this->gatewayConfigGlobPattern . 'payment_submethods.yaml';

		foreach ( glob( $globPattern ) as $path ) {
			$gatewayDirArray = explode( DIRECTORY_SEPARATOR, $path );
			$gateway = $gatewayDirArray[count( $gatewayDirArray ) - 3];
			$gatewayDirConfig = $yaml->parse( file_get_contents( $path ) );
			$extensionConfig = $this->getExtensionConfig( $gateway );
			$configCountries = [];
			if ( $extensionConfig !== null ) {
				foreach ( $gatewayDirConfig as $payment_submethod => $config ) {
					if ( array_key_exists( 'countries', $config ) ) {
						$configCountries = array_merge( $configCountries, $config['countries'] );
					}
				}

				foreach ( $configCountries as $country ) {
					$this->assertContains( $country, $extensionConfig, "$country in $gateway config" );
				}
			}
		}
	}

	public function testChooseGatewayByPrioritySingleRuleMatch() {
		$this->overrideConfigValues( [
			'DonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc' ],
					'gateways' => [ 'gravy' ],
				],
				[
					'gateways' => [ 'adyen', 'gravy', 'paypal_ec', 'dlocal', 'braintree' ],
				],
			],
		] );

		$testQueryParams = [
			'uselang' => "en",
			'language' => "en",
			'currency' => "GBP",
			'amount' => "10",
			'country' => "GB",
			'payment_method' => "cc",
		];

		$shortListedGateways = [ 'gravy', 'adyen', 'paypal' ];
		$expectedGateway = 'gravy';

		$processor = GatewayRouter::chooseGatewayByPriority(
			$shortListedGateways,
			$testQueryParams,
			MediaWikiServices::getInstance()->getMainConfig(),
			new NullLogger()
		);

		$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityMultiRuleMatch() {
			$this->overrideConfigValues( [
				'DonationInterfaceGatewayPriorityRules' => [
					[
						'conditions' => [ 'payment_method' => 'cc', 'utm_medium' => 'endowment' ],
						'gateways' => [ 'adyen' ],
					],
					[
						'gateways' => [ 'gravy', 'adyen', 'paypal_ec', 'dlocal', 'braintree' ],
					],
				],
			] );

			$testQueryParams = [
				'uselang' => "en",
				'language' => "en",
				'currency' => "USD",
				'amount' => "20",
				'country' => "US",
				'payment_method' => "cc",
				'utm_medium' => "endowment",
			];

			$shortListedGateways = [ 'gravy', 'adyen', 'paypal' ];
			$expectedGateway = 'adyen';

			$processor = GatewayRouter::chooseGatewayByPriority(
				$shortListedGateways,
				$testQueryParams,
				MediaWikiServices::getInstance()->getMainConfig(),
				new NullLogger()
			);

			$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityConditionValueArrayRuleMatch() {
		$this->overrideConfigValues( [
			'DonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'country' => [ 'US', 'GB', 'FR' ] ], // array as value
					'gateways' => [ 'gravy' ],
				],
				[
					'gateways' => [ 'adyen', 'gravy', 'paypal_ec', 'dlocal', 'braintree' ],
				],
			],
		] );

		$testQueryParams = [
			'currency' => "USD",
			'amount' => "20",
			'country' => "US",
			'payment_method' => "cc",
		];

		$shortListedGateways = [ 'gravy', 'adyen', 'paypal' ];
		$expectedGateway = 'gravy';

		$processor = GatewayRouter::chooseGatewayByPriority(
			$shortListedGateways,
			$testQueryParams,
			MediaWikiServices::getInstance()->getMainConfig(),
			new NullLogger()
		);

		$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityStopsAtFirstRuleMatch() {
		$this->overrideConfigValues( [
			'DonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc', 'utm_medium' => 'endowment' ],
					'gateways' => [ 'adyen' ],
				],
				[
					'conditions' => [ 'payment_method' => 'cc' ], // we shouldn't get to this one
					'gateways' => [ 'gravy' ],
				],
				[
					'gateways' => [ 'gravy', 'adyen', 'paypal_ec', 'dlocal', 'braintree' ],
				],
			],
		] );

		$testQueryParams = [
			'uselang' => "en",
			'language' => "en",
			'currency' => "USD",
			'amount' => "20",
			'country' => "US",
			'payment_method' => "cc",
			'utm_medium' => "endowment",
		];

		$shortListedGateways = [ 'gravy', 'adyen', 'paypal' ];
		$expectedGateway = 'adyen';

		$processor = GatewayRouter::chooseGatewayByPriority(
			$shortListedGateways,
			$testQueryParams,
			MediaWikiServices::getInstance()->getMainConfig(),
			new NullLogger()
		);

		$this->assertEquals( $expectedGateway, $processor );
	}

	public function testAdditionalParamatersPassThrough() {
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$params = [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
			'utm_source' => 'banner12345',
			'utm_campaign' => 'FR-campaign_12345',
			'amount' => '100'
		];

		$context->setRequest( new FauxRequest( $params, false ) );

		$context->setOutput( $newOutput );
		$context->setTitle( $newTitle );

		$fc = new GatewayChooser();
		$fc->execute( null );
		$fc->getOutput()->output();
		$url = $fc->getRequest()->response()->getheader( 'Location' );

		if ( !$url ) {
			$this->fail( 'No gateway returned for this configuration.' );
		}

		$parts = parse_url( $url );
		parse_str( $parts['query'], $query );
		$this->assertArrayContains( $params, $query, 'Should pass through params to querystring' );
	}

	/**
	 * Ensure we pass through the right recurring values
	 *
	 * @dataProvider recurringValueProvider
	 */
	public function testPassRecurringFalse( $qsVal, $expected ) {
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$params = [
			'payment_method' => 'cc',
			'country' => 'US',
			'currency' => 'USD',
		];

		if ( $qsVal !== null ) {
			$params['recurring'] = $qsVal;
		}

		$context->setRequest( new FauxRequest( $params, false ) );

		$context->setOutput( $newOutput );
		$context->setTitle( $newTitle );

		$fc = new GatewayChooser();
		$fc->execute( null );
		$fc->getOutput()->output();
		$url = $fc->getRequest()->response()->getheader( 'Location' );

		if ( !$url ) {
			$this->fail( 'No gateway returned for this configuration.' );
		}

		$parts = parse_url( $url );
		parse_str( $parts['query'], $query );
		$this->assertEquals( $expected, $query['recurring'] );
	}

	/**
	 * PayPal donations must be routed to Gravy, never to the legacy PayPal
	 * Express gateway. Exercises the full chooser pipeline against the real
	 * gateway configuration files.
	 *
	 * This test uses country 'MD' and currency 'USD' because we got multiple
	 * donations with this combination to PayPal Express gateway up to August 2026.
	 * So it was the combination picked to validate for the fix.
	 *
	 * @covers \MediaWiki\Extension\DonationInterface\Special\GatewayRouter::getSupportedGateways
	 * @covers \MediaWiki\Extension\DonationInterface\Special\GatewayRouter::chooseGatewayByPriority
	 */
	public function testChooseGravyPayPalInsteadOfPaypalEc() {
		$country = 'MD';
		$currency = 'USD';

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$supportedGateways = GatewayRouter::getSupportedGateways(
			$country,
			$currency,
			'paypal',
			null, // payment_submethod
			false, // recurring
			null, // variant
			$config
		);
		$this->assertContains(
			'gravy',
			$supportedGateways,
			"gravy should support paypal in $country / $currency"
		);

		$selectedGateway = GatewayRouter::chooseGatewayByPriority(
			$supportedGateways,
			[
				'country' => $country,
				'currency' => $currency,
				'payment_method' => 'paypal',
			],
			$config,
			new NullLogger()
		);

		$this->assertEquals( 'gravy', $selectedGateway );
	}

	public static function recurringValueProvider() {
		return [
			[ 'false', 0 ],
			[ '', 0 ],
			[ null, 0 ],
			[ '0', 0 ],
			[ 'true', 1 ],
			[ '1', 1 ]
		];
	}
}
