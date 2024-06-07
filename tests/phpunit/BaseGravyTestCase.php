<?php

use MediaWiki\Session\Token;

class BaseGravyTestCase extends DonationInterfaceTestCase {

	protected $testAdapterClass = GravyAdapter::class;

	/**
	 * @var \SmashPig\Tests\TestingProviderConfiguration
	 */
	protected $providerConfig;

	protected $clearToken = 'blahblah';

	protected $saltedToken;

	protected $redirectResult = 'sdhjiasdf89uy3q2rujhrasdfn789a3h24c89qad783h9a8cnyq9873245yhcq987yrhncawo87ryhcaok7wrya8o745ybso47egho47';

	protected function setUp(): void {
		parent::setUp();
		$this->saltedToken = md5( $this->clearToken ) . Token::SUFFIX;
		$this->providerConfig = $this->setSmashPigProvider( 'gravy' );
		$this->setMwGlobals( [
			'wgGravyGatewayEnabled' => true,
			'wgGravyGatewayCustomFiltersFunctions' => [
				'getCVVResult' => 10,
				'getAVSResult' => 50,
			],
			'wgDonationInterfaceGatewayAdapters' => [
				'gravy' => GravyAdapter::class
			]
		] );
	}
}
