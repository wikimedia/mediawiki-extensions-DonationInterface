<?php
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
 */

/**
 * @group Fundraising
 * @group DonationInterface
 * @group Localisation
 */
class DonorLocaleTest extends DonationInterfaceTestCase {

	/**
	 * @var GatewayType
	 */
	protected $adapter;

	/**
	 * @var DonorLocale
	 */
	protected $donorLocale;

	/**
	 * @var array
	 */
	protected $normalized;

	/**
	 * @var array
	 */
	protected $staged;

	public function setUp() {
		parent::setUp();

		$this->setUpRequest( [
			'country' => 'US',
			'uselang' => 'en',
		] );

		$this->normalized = [
			'language' => 'en',
			'country' => 'US',
			'currency' => 'USD',
		];
		$this->staged = [];
		$this->adapter = new TestingGenericAdapter();
		$this->donorLocale = new DonorLocale();
	}

	protected function stage() {
		$this->donorLocale->stage(
			$this->adapter, $this->normalized, $this->staged
		);
	}

	public function testStageSimple() {
		$this->stage();
		$this->assertEquals( 'en_US', $this->staged['language'] );
	}

	/**
	 * TODO: should this stage anyway, with no suffix?
	 */
	public function testNoCountrySet() {
		$this->normalized = [
			'language' => 'en'
		];
		$this->stage();
		$this->assertFalse( isset( $this->staged['language'] ) );
	}

	public function testStageVariant() {
		$this->normalized['language'] = 'zh-ha';
		$this->stage();
		$this->assertEquals( 'zh_US', $this->staged['language'] );
	}

	public function testUnstage() {
		$this->staged['language'] = 'fr_CA';
		$this->donorLocale->unstage(
			$this->adapter, $this->staged, $this->normalized
		);
		$this->assertEquals( 'fr', $this->normalized['language'] );
	}
}
