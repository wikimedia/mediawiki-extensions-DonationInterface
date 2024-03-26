<?php

namespace MediaWiki\Extension\DonationInterface\Tests\RecurUpgrade;

use MediaWiki\Extension\DonationInterface\RecurUpgrade\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase {

	protected const TOKEN = 'testSessionToken';

	protected function validate(
		array $params,
		bool $posted,
		array $sessionData = [],
		float $maxUSD = 1000
	): bool {
		$session = $this->createMock( 'MediaWiki\Session\Session' );
		$session->method( 'get' )
			->with( \RecurUpgrade::DONOR_DATA )
			->willReturn( $sessionData );
		$token = $this->createMock( 'MediaWiki\Session\Token' );
		$token->method( 'match' )
			->with( self::TOKEN )
			->willReturn( true );
		$session->method( 'getToken' )
			->willReturn( $token );
		$config = $this->createMock( 'Config' );
		$config->method( 'get' )
			->with( 'DonationInterfaceRecurringUpgradeMaxUSD' )
			->willReturn( $maxUSD );
		$validator = new Validator( $session, $config );
		return $validator->validate( $params, $posted );
	}

	protected function getCommonParameters(): array {
		return [
			'checksum' => '0c9218b38a35df1e0b3307b6bf47e800_1710862463_168',
			'contact_id' => '207',
			'title' => 'Special:RecurUpgrade',
			'uselang' => 'en',
			'variant' => 'v01',
			'wmf_campaign' => 'blarg_123',
			'wmf_medium' => 'email',
		];
	}

	protected function getCommonParametersForSubmit(): array {
		return $this->getCommonParameters() + [
			'token' => self::TOKEN,
			'submit' => 'save'
		];
	}

	/**
	 * Initial form load with normal parameters
	 *
	 * @return void
	 */
	public function testValidParamsNotPosted() {
		$result = $this->validate( $this->getCommonParameters(), false );
		$this->assertTrue( $result );
	}

	/**
	 * Initial form load with sketchy parameters
	 *
	 * @return void
	 */
	public function testSketchyCharactersInParamsNotPosted() {
		$result = $this->validate( [
			'checksum' => '0c9218b38a35df1e0b3307b6bf47e800_1710862463_168',
			'contact_id' => "123'; DROP TABLE `students`",
			'title' => 'Special:RecurUpgrade',
			'variant' => '../../../etc/passwd',
		], false );
		$this->assertFalse( $result );
	}

	/**
	 * Initial form load with normal parameters and submit = cancel (the decline link in the email)
	 *
	 * @return void
	 */
	public function testValidParamsWithCancelNotPosted() {
		$result = $this->validate(
			$this->getCommonParameters() + [ 'submit' => 'cancel' ], false
		);
		$this->assertTrue( $result );
	}

	/**
	 * Pressing cancel on the loaded form
	 *
	 * @return void
	 */
	public function testValidParamsWithCancelPosted() {
		$result = $this->validate(
			$this->getCommonParameters() + [
				'submit' => 'cancel',
				'token' => self::TOKEN
			], true
		);
		$this->assertTrue( $result );
	}

	/**
	 * Submitting the form without including an amount should fail
	 *
	 * @return void
	 */
	public function testPostedWithNoAmount() {
		$result = $this->validate( $this->getCommonParametersForSubmit(), true );
		$this->assertFalse( $result );
	}

	/**
	 * Submitting the form with a good amount should work
	 *
	 * @return void
	 */
	public function testPostedWithGoodAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => '1.00' ], true
		);
		$this->assertTrue( $result );
	}

	/**
	 * Submitting the form with a negative amount should fail
	 *
	 * @return void
	 */
	public function testPostedWithNegativeAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => '-1.00' ], true
		);
		$this->assertFalse( $result );
	}

	/**
	 * Submitting the form with a good 'other' amount should work
	 *
	 * @return void
	 */
	public function testPostedWithOtherAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => 'other', 'upgrade_amount_other' => '0.01' ],
			true
		);
		$this->assertTrue( $result );
	}

	/**
	 * Submitting the form with a negative 'other' amount should work
	 *
	 * @return void
	 */
	public function testPostedWithNegativeOtherAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => 'other', 'upgrade_amount_other' => '-0.01' ],
			true
		);
		$this->assertFalse( $result );
	}

	/**
	 * Submitting the form with amount='other' but no other_amount should fail
	 *
	 * @return void
	 */
	public function testPostedMissingOtherAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => 'other' ], true
		);
		$this->assertFalse( $result );
	}

	/**
	 * Submitting the form with an amount that's too large should fail
	 *
	 * @return void
	 */
	public function testPostedWithHugeAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => '500000' ], true
		);
		$this->assertFalse( $result );
	}

	/**
	 * Submitting the form with an other_amount that's too large should fail
	 *
	 * @return void
	 */
	public function testPostedWithHugeOtherAmount() {
		$result = $this->validate(
			$this->getCommonParametersForSubmit() + [ 'upgrade_amount' => 'other', 'upgrade_amount_other' => '500000' ],
			true
		);
		$this->assertFalse( $result );
	}
}
