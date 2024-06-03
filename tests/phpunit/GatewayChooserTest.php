<?php
use MediaWiki\MediaWikiServices;
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
 */
class DonationInterface_GatewayChooserTest extends DonationInterfaceTestCase {

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

		$this->setMwGlobals( [
			'wgDonationInterfaceEnableGatewayChooser' => true,
			'wgIngenicoGatewayEnabled' => true,
			'wgDlocalGatewayEnabled' => true,
			'wgBraintreeGatewayEnabled' => true,
			'wgPaypalExpressGatewayEnabled' => true,
			'wgAdyenCheckoutGatewayEnabled' => true,
			'wgAmazonGatewayEnabled' => true,
			'wgDonationInterfaceGatewayAdapters' => [
				'ingenico' => 'IngenicoAdapter',
				'amazon' => 'AmazonAdapter',
				'adyen' => 'AdyenCheckoutAdapter',
				'paypal_ec' => 'PaypalExpressAdapter',
				'braintree' => 'BraintreeAdapter',
				'dlocal' => 'DlocalAdapter',
				'gravy' => 'GravyAdapter',
			]
		] );
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$this->dir = $config->get( 'ExtensionDirectory' ) . DIRECTORY_SEPARATOR . 'DonationInterface' . DIRECTORY_SEPARATOR;
		$this->gatewayConfigGlobPattern = $this->dir . '*' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
	}

	public function testMaintenanceMode_Redirect() {
		$this->setMwGlobals( [
			'wgDonationInterfaceFundraiserMaintenance' => true,
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
		$this->setMwGlobals( [ 'wgDonationInterfaceChooserProblemURL' => 'https://test.example' ] );
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
		$this->assertArraySubmapSame( $params, $query, 'Should pass through params to querystring' );
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
			// paypal payment method should be routed to PaypalExpressGateway
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD' ], 'PaypalExpressGateway' ],
			[ [ 'payment_method' => 'paypal', 'country' => 'CN', 'currency' => 'USD' ], 'PaypalExpressGateway' ],
			// When country is supported at the gateway level but not at the method level, don't redirect
			[ [ 'payment_method' => 'paypal', 'country' => 'GH', 'currency' => 'GHS' ], null ],
			// amazon payment method is only provided by AmazonGateway
			[ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD' ], 'AmazonGateway' ],

			// Ensure paypal and amazon gateways are selected even if the currency is unsupported.
			// For both, for these test cases to work, CLP currency must not be included in
			// currencies.yaml, and in general.yaml, gateway_chooser/still_include_if_currency_is_not_supported
			// must be true.
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'CLP' ], 'PaypalExpressGateway' ],
			[ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'CLP' ], 'AmazonGateway' ],

			// bank transfer methods, all currently processed via DLocal
			[ [ 'payment_method' => 'bt', 'country' => 'AR', 'currency' => 'ARS' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'BR', 'currency' => 'BRL' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'CO', 'currency' => 'COP' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'CL', 'currency' => 'CLP' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'IN', 'currency' => 'INR' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'PE', 'currency' => 'PEN' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'bt', 'country' => 'ZA', 'currency' => 'ZAR' ], 'DlocalGateway' ],

			// cash methods, all currently processed via DLocal
			[ [ 'payment_method' => 'cash', 'country' => 'AR', 'currency' => 'ARS' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'BR', 'currency' => 'BRL' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'CO', 'currency' => 'COP' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'MX', 'currency' => 'INR' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'PE', 'currency' => 'PEN' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cash', 'country' => 'UY', 'currency' => 'ZAR' ], 'DlocalGateway' ],

			// iDEAL (NL-only realtime bank transfer)
			[ [ 'payment_method' => 'rtbt', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			// Should work with submethod specified
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'sepadirectdebit', 'country' => 'DE', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			// Test country restriction on submethod
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal', 'country' => 'FR', 'currency' => 'EUR' ], null ],
			[ [ 'payment_method' => 'rtbt', 'payment_submethod' => 'sepadirectdebit', 'country' => 'FR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],

			// Recurring: only paypal, ingenico and adyen got recurring
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR', 'recurring' => '1' ], 'AdyenCheckoutGateway' ], // adyen recurring
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY', 'recurring' => '1' ], 'AdyenCheckoutGateway' ], // ingenico recurring
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'PaypalExpressGateway' ], // paypal recurring
			// Amazon recurring is not finished; see T107391
			// [ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'AmazonGateway' ],

			// LATAM recurring should use dlocal
			[ [ 'payment_method' => 'cc', 'country' => 'AR', 'currency' => 'ARS', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BR', 'currency' => 'BRL', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CL', 'currency' => 'CLP', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CO', 'currency' => 'COP', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MX', 'currency' => 'MXN', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PE', 'currency' => 'PEN', 'recurring' => '1' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UY', 'currency' => 'UYU', 'recurring' => '1' ], 'DlocalGateway' ],

			// below cc payment_method test cases for countries by national currency; originally generated by
			// `php extensions/DonationInterface/maintenance/TestCaseMaintenance.php`
			[ [ 'payment_method' => 'cc', 'country' => 'AD', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AE', 'currency' => 'AED' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AG', 'currency' => 'XCD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AL', 'currency' => 'ALL' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AR', 'currency' => 'ARS' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AS', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AT', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AU', 'currency' => 'AUD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BB', 'currency' => 'BBD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BD', 'currency' => 'BDT' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BE', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BG', 'currency' => 'BGN' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BH', 'currency' => 'BHD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BM', 'currency' => 'BMD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BO', 'currency' => 'BOB' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BR', 'currency' => 'BRL' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BZ', 'currency' => 'BZD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CA', 'currency' => 'CAD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CH', 'currency' => 'CHF' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CK', 'currency' => 'NZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CL', 'currency' => 'CLP' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CN', 'currency' => 'CNY' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CO', 'currency' => 'COP' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CR', 'currency' => 'CRC' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CY', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CZ', 'currency' => 'CZK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DE', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DK', 'currency' => 'DKK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DM', 'currency' => 'XCD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DO', 'currency' => 'DOP' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DZ', 'currency' => 'DZD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EC', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EE', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EG', 'currency' => 'EGP' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ES', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FI', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FJ', 'currency' => 'FJD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FO', 'currency' => 'DKK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GB', 'currency' => 'GBP' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GD', 'currency' => 'XCD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GF', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GL', 'currency' => 'DKK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GT', 'currency' => 'GTQ' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GU', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HK', 'currency' => 'HKD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HN', 'currency' => 'HNL' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HR', 'currency' => 'HRK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HU', 'currency' => 'HUF' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ID', 'currency' => 'IDR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IE', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IL', 'currency' => 'ILS' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IN', 'currency' => 'INR' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IT', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JM', 'currency' => 'JMD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JO', 'currency' => 'JOD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KE', 'currency' => 'KES' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KI', 'currency' => 'AUD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KR', 'currency' => 'KRW' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KW', 'currency' => 'KWD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KZ', 'currency' => 'KZT' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LB', 'currency' => 'LBP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LI', 'currency' => 'CHF' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LK', 'currency' => 'LKR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LU', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LV', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MA', 'currency' => 'MAD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MH', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MK', 'currency' => 'MKD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MP', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MV', 'currency' => 'MVR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MX', 'currency' => 'MXN' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MY', 'currency' => 'MYR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NI', 'currency' => 'NIO' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NO', 'currency' => 'NOK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NR', 'currency' => 'AUD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NZ', 'currency' => 'NZD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'OM', 'currency' => 'OMR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PA', 'currency' => 'PAB' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PE', 'currency' => 'PEN' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PH', 'currency' => 'PHP' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PK', 'currency' => 'PKR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PL', 'currency' => 'PLN' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PR', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PS', 'currency' => 'ILS' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PT', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PW', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PY', 'currency' => 'PYG' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'QA', 'currency' => 'QAR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RE', 'currency' => 'NZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RO', 'currency' => 'RON' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SA', 'currency' => 'SAR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SC', 'currency' => 'SCR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SE', 'currency' => 'SEK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SK', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SG', 'currency' => 'SGD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TH', 'currency' => 'THB' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TN', 'currency' => 'TND' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TR', 'currency' => 'TRY' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TT', 'currency' => 'TTD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TW', 'currency' => 'TWD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UA', 'currency' => 'UAH' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'US', 'currency' => 'USD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UY', 'currency' => 'UYU' ], 'DlocalGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VA', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VE', 'currency' => 'VEF' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VI', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VN', 'currency' => 'VND' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VU', 'currency' => 'VUV' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ZA', 'currency' => 'ZAR' ], 'DlocalGateway' ],

			// apple pay
			[ [ 'payment_method' => 'apple', 'country' => 'AU', 'currency' => 'AUD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'CA', 'currency' => 'CAD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'FR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'GB', 'currency' => 'GBP' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IE', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IL', 'currency' => 'ILS' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'IT', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'JP', 'currency' => 'JPY' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'NZ', 'currency' => 'NZD' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'SE', 'currency' => 'SEK' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'UA', 'currency' => 'UAH' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'apple', 'country' => 'US', 'currency' => 'USD' ], 'AdyenCheckoutGateway' ],
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

				foreach ( $configCountries as $key => $value ) {
					$this->assertContains( $key, $extensionConfig, "$key in $gateway config" );
				}
			}
		}
	}

	public function testChooseGatewayByPrioritySingleRuleMatch() {
		$this->setMwGlobals( [
			'wgDonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc' ],
					'gateways' => [ 'ingenico' ]
				],
				[
					'gateways' => [ 'adyen', 'ingenico', 'paypal_ec', 'amazon', 'dlocal' ]
				]
			]
		] );

		$testQueryParams = [
			'uselang' => "en",
			'language' => "en",
			'currency' => "GBP",
			'amount' => "10",
			'country' => "GB",
			'payment_method' => "cc",
		];

		$shortListedGateways = [ 'ingenico', 'adyen', 'paypal' ];
		$expectedGateway = 'ingenico';

		$GatewayChooser = new GatewayChooser();
		$processor = $GatewayChooser->chooseGatewayByPriority( $shortListedGateways, $testQueryParams );

		$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityMultiRuleMatch() {
			$this->setMwGlobals( [
				'wgDonationInterfaceGatewayPriorityRules' => [
					[
						'conditions' => [ 'payment_method' => 'cc', 'utm_medium' => 'endowment' ],
						'gateways' => [ 'adyen' ]
					],
					[
						'gateways' => [ 'ingenico', 'adyen', 'paypal_ec', 'amazon', 'dlocal' ]
					]
				]
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

			$shortListedGateways = [ 'ingenico', 'adyen', 'paypal' ];
			$expectedGateway = 'adyen';

			$GatewayChooser = new GatewayChooser();
			$processor = $GatewayChooser->chooseGatewayByPriority( $shortListedGateways, $testQueryParams );

			$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityConditionValueArrayRuleMatch() {
		$this->setMwGlobals( [
			'wgDonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'country' => [ 'US', 'GB', 'FR' ] ], // array as value
					'gateways' => [ 'ingenico' ]
				],
				[
					'gateways' => [ 'adyen', 'ingenico', 'paypal_ec', 'amazon', 'dlocal' ]
				]
			]
		] );

		$testQueryParams = [
			'currency' => "USD",
			'amount' => "20",
			'country' => "US",
			'payment_method' => "cc",
		];

		$shortListedGateways = [ 'ingenico', 'adyen', 'paypal' ];
		$expectedGateway = 'ingenico';

		$GatewayChooser = new GatewayChooser();
		$processor = $GatewayChooser->chooseGatewayByPriority( $shortListedGateways, $testQueryParams );

		$this->assertEquals( $expectedGateway, $processor );
	}

	public function testChooseGatewayByPriorityStopsAtFirstRuleMatch() {
		$this->setMwGlobals( [
			'wgDonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc', 'utm_medium' => 'endowment' ],
					'gateways' => [ 'adyen' ]
				],
				[
					'conditions' => [ 'payment_method' => 'cc' ], // we shouldn't get to this one
					'gateways' => [ 'ingenico' ]
				],
				[
					'gateways' => [ 'ingenico', 'adyen', 'paypal_ec', 'amazon', 'dlocal' ]
				]
			]
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

		$shortListedGateways = [ 'ingenico', 'adyen', 'paypal' ];
		$expectedGateway = 'adyen';

		$GatewayChooser = new GatewayChooser();
		$processor = $GatewayChooser->chooseGatewayByPriority( $shortListedGateways, $testQueryParams );

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
		$this->assertArraySubmapSame( $params, $query, 'Should pass through params to querystring' );
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
