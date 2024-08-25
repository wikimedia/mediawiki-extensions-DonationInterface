<?php

use MediaWiki\Session\Token;

class BaseAdyenCheckoutTestCase extends DonationInterfaceTestCase {

	/** @inheritDoc */
	protected $testAdapterClass = AdyenCheckoutAdapter::class;

	/**
	 * @var \SmashPig\Tests\TestingProviderConfiguration
	 */
	protected $providerConfig;

	/** @var string */
	protected $clearToken = 'blahblah';

	/** @var string */
	protected $saltedToken;

	/** @var string */
	protected $redirectResult = 'sdhjiasdf89uy3q2rujhrasdfn789a3h24c89qad783h9a8cnyq9873245yhcq987yrhncawo87ryhcaok7wrya8o745ybso47egho47';

	protected function setUp(): void {
		parent::setUp();
		$this->saltedToken = md5( $this->clearToken ) . Token::SUFFIX;
		$this->providerConfig = $this->setSmashPigProvider( 'adyen' );
		$this->overrideConfigValues( [
			'AdyenCheckoutGatewayEnabled' => true,
			'AdyenCheckoutGatewayCustomFiltersFunctions' => [
				'getCVVResult' => 10,
				'getAVSResult' => 50,
			],
			'DonationInterfaceGatewayAdapters' => [
				'adyen' => AdyenCheckoutAdapter::class
			],
		] );
	}
}
