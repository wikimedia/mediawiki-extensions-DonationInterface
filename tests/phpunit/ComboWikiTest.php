<?php
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Special\ComboWiki;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group ComboWiki
 * @group Database
 * @covers \MediaWiki\Extension\DonationInterface\Special\ComboWiki
 */
class ComboWikiTest extends DonationInterfaceTestCase {

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
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
			'DonationInterfaceGatewayPriorityRules' => [
				[
					'conditions' => [ 'payment_method' => 'cc' ],
					'gateways' => [ 'gravy' ],
				],
				[
					'conditions' => [ 'payment_method' => 'paypal' ],
					'gateways' => [ 'paypal_ec' ],
				],
				[
					'conditions' => [ 'payment_method' => 'venmo' ],
					'gateways' => [ 'braintree' ],
				],
			],
		] );
	}

	public function testChoosesGravyForCreditCard(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'cc',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'gravy'
		);
	}

	public function testChoosesPaypalForPaypal(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'paypal',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'paypal_ec'
		);
	}

	public function testChoosesBraintreeForVenmo(): void {
		$this->assertChosenGateway(
			[
				'payment_method' => 'venmo',
				'country' => 'US',
				'currency' => 'USD',
				'recurring' => '0',
			],
			'braintree'
		);
	}

	private function assertChosenGateway( array $params, string $expectedGateway ): void {
		$context = RequestContext::getMain();
		$context->setRequest( new FauxRequest( $params, false ) );
		$context->setTitle( Title::newFromText( 'Special:ComboWiki' ) );

		$comboWiki = new ComboWiki();
		$vars = [];
		$comboWiki->setClientVariables( $vars );

		$this->assertEquals( $expectedGateway, $vars['comboWiki']['gateway'] );
	}
}
