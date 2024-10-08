<?php

use MediaWiki\Session\Token;

class BaseGravyTestCase extends DonationInterfaceTestCase {

	/** @inheritDoc */
	protected $testAdapterClass = GravyAdapter::class;

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
		$this->providerConfig = $this->setSmashPigProvider( 'gravy' );
		$this->overrideConfigValues( [
			'GravyGatewayEnabled' => true,
			'GravyGatewayCustomFiltersFunctions' => [
				'getCVVResult' => 10,
				'getAVSResult' => 50,
			],
			'DonationInterfaceGatewayAdapters' => [
				'gravy' => GravyAdapter::class
			],
		] );
	}
}
