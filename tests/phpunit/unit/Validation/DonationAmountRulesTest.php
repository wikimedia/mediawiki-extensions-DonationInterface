<?php

namespace MediaWiki\Extension\DonationInterface\Tests\Validation;

use MediaWiki\Extension\DonationInterface\Configuration\GatewayConfigurationFactory;
use MediaWiki\Extension\DonationInterface\Validation\DonationAmountRules;
use PHPUnit\Framework\TestCase;

/**
 * @group DonationInterface
 * @covers \MediaWiki\Extension\DonationInterface\Validation\DonationAmountRules
 */
class DonationAmountRulesTest extends TestCase {

	private const GB_RECURRING_RULE = [
		'conditions' => [ 'country' => 'GB', 'recurring' => true ],
		'min' => 2,
		'max' => 5000,
	];

	private const EUR_RULE = [
		'conditions' => [ 'currency' => 'EUR' ],
		'min' => 1,
		'max' => 8000,
	];

	private const FALLBACK_RULE = [
		'min' => 1,
		'max' => 12000,
	];

	private const RULES = [ self::GB_RECURRING_RULE, self::EUR_RULE, self::FALLBACK_RULE ];

	public function testMatchesRuleWhenAllConditionsMatch(): void {
		$donationData = [ 'country' => 'GB', 'recurring' => true ];
		// Country and recurring both match the GB rule's conditions, so that rule is returned
		$this->assertSame(
			self::GB_RECURRING_RULE,
			DonationAmountRules::lookupMatchingDonationRules( self::RULES, $donationData )
		);
	}

	public function testFirstMatchingRuleWins(): void {
		$donationData = [ 'country' => 'GB', 'recurring' => true, 'currency' => 'EUR' ];
		// This data matches both the GB rule and the EUR rule, so getting the GB rule back
		// proves rules are checked in order and the first match is used
		$this->assertSame(
			self::GB_RECURRING_RULE,
			DonationAmountRules::lookupMatchingDonationRules( self::RULES, $donationData )
		);
	}

	public function testSkipsRuleWhenAConditionHasADifferentValue(): void {
		$donationData = [ 'country' => 'FR', 'recurring' => true, 'currency' => 'EUR' ];
		// FR does not equal the GB rule's country, so the GB rule is skipped and the EUR rule matches
		$this->assertSame(
			self::EUR_RULE,
			DonationAmountRules::lookupMatchingDonationRules( self::RULES, $donationData )
		);
	}

	public function testSkipsRuleWhenAConditionIsMissingFromDonationData(): void {
		$donationData = [ 'country' => 'GB' ];
		// Country matches the GB rule but recurring is absent, so the GB rule is skipped.
		// Getting the fallback proves a missing field counts as a failed condition
		$this->assertSame(
			self::FALLBACK_RULE,
			DonationAmountRules::lookupMatchingDonationRules( self::RULES, $donationData )
		);
	}

	public function testFallsBackToRuleWithoutConditions(): void {
		$donationData = [ 'country' => 'US', 'currency' => 'USD' ];
		// Neither conditional rule matches, so the rule with no conditions is returned
		$this->assertSame(
			self::FALLBACK_RULE,
			DonationAmountRules::lookupMatchingDonationRules( self::RULES, $donationData )
		);
	}

	public function testReturnsEmptyArrayWhenNoRuleMatches(): void {
		$rulesWithoutFallback = [ self::GB_RECURRING_RULE, self::EUR_RULE ];
		// With no fallback rule to catch it, a donation that matches nothing gets an empty array
		$this->assertSame(
			[],
			DonationAmountRules::lookupMatchingDonationRules( $rulesWithoutFallback, [ 'country' => 'US' ] )
		);
	}

	public function testGetDonationRulesUsesTheGatewayConfiguration(): void {
		$configurationFactory = $this->createMock( GatewayConfigurationFactory::class );
		// Proves the rules are read from the requested gateway's configuration, exactly once
		$configurationFactory->expects( $this->once() )
			->method( 'getConfigurationForGatewayAndVariant' )
			->with( 'gravy' )
			->willReturn( [ 'donation_rules' => self::RULES ] );
		$donationAmountRules = new DonationAmountRules( $configurationFactory );

		$rule = $donationAmountRules->getDonationRules( 'gravy', [ 'country' => 'GB', 'recurring' => true ] );

		// Proves the donation data is matched against the rules from that configuration
		$this->assertSame( self::GB_RECURRING_RULE, $rule );
	}
}
