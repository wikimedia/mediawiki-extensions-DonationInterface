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
			'wgAstroPayGatewayEnabled' => true,
			'wgPaypalExpressGatewayEnabled' => true,
			'wgAdyenCheckoutGatewayEnabled' => true,
			'wgGlobalCollectGatewayEnabled' => true,
			'wgAmazonGatewayEnabled' => true,
			'wgDonationInterfaceGatewayAdapters' => [
				'globalcollect' => 'GlobalCollectAdapter',
				'ingenico' => 'IngenicoAdapter',
				'amazon' => 'AmazonAdapter',
				'adyen' => 'AdyenCheckoutAdapter',
				'astropay' => 'AstroPayAdapter',
				'paypal_ec' => 'PaypalExpressAdapter',
				'braintree' => 'BraintreeAdapter'
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
	 * @dataProvider expectedGatewayDataProvider
	 */
	public function testAssertExpectedGateway( $params, $expectedSpecialGateway ) {
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
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
		$gateway = str_replace( 'Special:', '', $query['title'] );

		$this->assertEquals( $expectedSpecialGateway, $gateway, 'Gateway not match' );
		$this->assertArraySubmapSame( $params, $query, 'Should pass through params to querystring' );
	}

	public function expectedGatewayDataProvider() {
		// Gateways:
		// AstroPayGateway
		// AmazonGateway
		// AdyenCheckoutGateway
		// PaypalExpressGateway
		// IngenicoGateway

		// TODO Add test cases for Google Pay and cash payment methods

		return [
			// paypal payement method is only provided by PaypalExpressGateway
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD' ], 'PaypalExpressGateway' ],
			// amazon payement method is only provided by AmazonGateway
			[ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD' ], 'AmazonGateway' ], // amazon

			// bank transfer methods
			// TODO expand this
			[ [ 'payment_method' => 'bt', 'country' => 'BR', 'currency' => 'BRL' ], 'AstroPayGateway' ], // bt astropay
			[ [ 'payment_method' => 'rtbt', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			// obt (BPay) removed, see T309475
			// [ [ 'payment_method' => 'obt', 'country' => 'AU', 'currency' => 'AUD' ], 'GlobalCollectGateway' ],

			// Recurring: only paypal, ingenico and adyen got recurring
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR', 'recurring' => '1' ], 'AdyenCheckoutGateway' ], // adyen recurring
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY', 'recurring' => '1' ], 'IngenicoGateway' ], // ingenico recurring
			[ [ 'payment_method' => 'paypal', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'PaypalExpressGateway' ], // paypal recurring
			// Amazon recurring is not finished; see T107391
			// [ [ 'payment_method' => 'amazon', 'country' => 'US', 'currency' => 'USD', 'recurring' => '1' ], 'AmazonGateway' ],

			// below cc payment_method test cases for countries by national currency; originally generated by
			// `php extensions/DonationInterface/maintenance/TestCaseMaintenance.php`
			[ [ 'payment_method' => 'cc', 'country' => 'AD', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AE', 'currency' => 'AED' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AG', 'currency' => 'XCD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AL', 'currency' => 'ALL' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AR', 'currency' => 'ARS' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AS', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'AU', 'currency' => 'AUD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BB', 'currency' => 'BBD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BD', 'currency' => 'BDT' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BE', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BG', 'currency' => 'BGN' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BH', 'currency' => 'BHD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BM', 'currency' => 'BMD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BO', 'currency' => 'BOB' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BR', 'currency' => 'BRL' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'BZ', 'currency' => 'BZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CA', 'currency' => 'CAD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CH', 'currency' => 'CHF' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CK', 'currency' => 'NZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CL', 'currency' => 'CLP' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CN', 'currency' => 'CNY' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CO', 'currency' => 'COP' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CR', 'currency' => 'CRC' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CY', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'CZ', 'currency' => 'CZK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DE', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DK', 'currency' => 'DKK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DM', 'currency' => 'XCD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DO', 'currency' => 'DOP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'DZ', 'currency' => 'DZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EC', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EE', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'EG', 'currency' => 'EGP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FI', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FJ', 'currency' => 'FJD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FO', 'currency' => 'DKK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'FR', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GB', 'currency' => 'GBP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GD', 'currency' => 'XCD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GF', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GL', 'currency' => 'DKK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GR', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GT', 'currency' => 'GTQ' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'GU', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HK', 'currency' => 'HKD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HN', 'currency' => 'HNL' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HR', 'currency' => 'HRK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'HU', 'currency' => 'HUF' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ID', 'currency' => 'IDR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IE', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IL', 'currency' => 'ILS' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IN', 'currency' => 'INR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'IT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JM', 'currency' => 'JMD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JO', 'currency' => 'JOD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'JP', 'currency' => 'JPY' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KE', 'currency' => 'KES' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KI', 'currency' => 'AUD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KR', 'currency' => 'KRW' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KW', 'currency' => 'KWD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'KZ', 'currency' => 'KZT' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LB', 'currency' => 'LBP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LI', 'currency' => 'CHF' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LK', 'currency' => 'LKR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'LU', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MA', 'currency' => 'MAD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MH', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MK', 'currency' => 'MKD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MP', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MV', 'currency' => 'MVR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MX', 'currency' => 'MXN' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'MY', 'currency' => 'MYR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NI', 'currency' => 'NIO' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NL', 'currency' => 'EUR' ], 'AdyenCheckoutGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NO', 'currency' => 'NOK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NR', 'currency' => 'AUD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'NZ', 'currency' => 'NZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'OM', 'currency' => 'OMR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PA', 'currency' => 'PAB' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PE', 'currency' => 'PEN' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PH', 'currency' => 'PHP' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PK', 'currency' => 'PKR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PL', 'currency' => 'PLN' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PR', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PS', 'currency' => 'ILS' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PT', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PW', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'PY', 'currency' => 'PYG' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'QA', 'currency' => 'QAR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RE', 'currency' => 'NZD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'RO', 'currency' => 'RON' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SA', 'currency' => 'SAR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SC', 'currency' => 'SCR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SE', 'currency' => 'SEK' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'SG', 'currency' => 'SGD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TH', 'currency' => 'THB' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TN', 'currency' => 'TND' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TR', 'currency' => 'TRY' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TT', 'currency' => 'TTD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'TW', 'currency' => 'TWD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UA', 'currency' => 'UAH' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'US', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'UY', 'currency' => 'UYU' ], 'AstroPayGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VA', 'currency' => 'EUR' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VE', 'currency' => 'VEF' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VI', 'currency' => 'USD' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VN', 'currency' => 'VND' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'VU', 'currency' => 'VUV' ], 'IngenicoGateway' ],
			[ [ 'payment_method' => 'cc', 'country' => 'ZA', 'currency' => 'ZAR' ], 'AstroPayGateway' ],

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

	/**
	 * currency should take precedence over currency_code, payment_method
	 * over paymentmethod, etc.
	 */
	public function testPreferCanonicalParams() {
		$assertNodes = [
			'headers' => [
				'Location' => function ( $val ) {
					$qs = [];
					parse_str( parse_url( $val, PHP_URL_QUERY ), $qs );
					$this->assertEquals( 'Special:PaypalExpressGateway', $qs['title'], 'Wrong form' );
				}
			],
		];
		$initial = [
			'language' => 'en',
			'payment_method' => 'paypal',
			'paymentmethod' => 'amazon',
			'country' => 'US',
		];
		$this->verifyFormOutput( 'GatewayChooser', $initial, $assertNodes, false );
	}

	/**
	 * Make sure none of the payment form settings are horribly broken.
	 */
	public function testBuildAllFormUrls() {
		global $wgDonationInterfaceAllowedHtmlForms;
		foreach ( $wgDonationInterfaceAllowedHtmlForms as $ffname => $config ) {
			if ( empty( $config['special_type'] ) || $config['special_type'] != 'error' ) {
				$url = GatewayChooser::buildPaymentsFormURL( $ffname );
				$this->assertNotNull( $url );
			}
		}
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
					'gateways' => [ 'adyen', 'ingenico', 'paypal_ec', 'amazon', 'astropay' ]
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
						'gateways' => [ 'ingenico', 'adyen', 'paypal_ec', 'amazon', 'astropay' ]
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
					'conditions' => [ 'country' => [ 'US','GB','FR' ] ], // array as value
					'gateways' => [ 'ingenico' ]
				],
				[
					'gateways' => [ 'adyen', 'ingenico', 'paypal_ec', 'amazon', 'astropay' ]
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
					'gateways' => [ 'ingenico', 'adyen', 'paypal_ec', 'amazon', 'astropay' ]
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
}
