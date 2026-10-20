<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\SessionId;
use MediaWiki\Title\Title;
use Psr\Log\NullLogger;

/**
 * Tests for gateway entries with a percentage in $wgDonationInterfaceGatewayPriorityRules.
 *
 * chooseGatewayByPriority() only compares gateway names, so most tests use names that
 * describe the gateway's role in the test, such as 'percentage_gateway'.
 *
 * For each entry with a percentage, the router draws a number from 1 to 100. It skips the
 * entry when the draw is more than the percentage, and otherwise chooses it (if supported).
 *
 * Most tests pass a fixed seed to chooseGatewayByPriority(). The seeds were picked by checking what
 * ( new Random\Randomizer( new Random\Engine\Mt19937( $seed ) ) )->getInt( 1, 100 )
 * returns. The names of the seed variables say which numbers each seed draws.
 *
 * @group DonationInterface
 * @covers \MediaWiki\Extension\DonationInterface\Configuration\GatewayRouter
 * @covers \GatewayChooser
 */
class GatewayRoutingProbabilisticTest extends DonationInterfaceTestCase {

	/**
	 * A donor whose draw is at or below the percentage gets the percentage gateway.
	 */
	public function testDonorInsidePercentageGetsPercentageGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDrawsSeven = 18;

		// The donor is not in FR or DE, so the card rule with the 25% entry is used.
		// The draw is 7, which is not more than 25, so the percentage gateway is chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDrawsSeven
		);

		$this->assertSame( 'percentage_gateway', $chosenGateway );
	}

	/**
	 * A donor whose draw is above the percentage gets the next gateway in the list.
	 */
	public function testDonorOutsidePercentageGetsFallbackGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDraws46 = 1;

		// The card rule is used. The draw is 46, which is more than 25, so the 25% entry
		// is skipped and the next entry in the rule, fallback_gateway, is chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDraws46
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * The same seed (the same session) gets the same gateway every time.
	 */
	public function testSameSeedGetsSameGatewayEveryTime(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$supportedGateways = [ 'fallback_gateway', 'percentage_gateway' ];
		$seedThatDrawsSeven = 18;

		// Each call starts a new random number generator from the same seed, so each call
		// draws 7 and chooses the percentage gateway. On the site the seed comes from the
		// session ID, so a donor keeps the same gateway for the whole session.
		$chosenGateways = [
			$router->chooseGatewayByPriority( $supportedGateways, $cardDonorInUS, new NullLogger(), $seedThatDrawsSeven ),
			$router->chooseGatewayByPriority( $supportedGateways, $cardDonorInUS, new NullLogger(), $seedThatDrawsSeven ),
			$router->chooseGatewayByPriority( $supportedGateways, $cardDonorInUS, new NullLogger(), $seedThatDrawsSeven ),
		];

		$this->assertSame( [ 'percentage_gateway', 'percentage_gateway', 'percentage_gateway' ], $chosenGateways );
	}

	/**
	 * Donors who match an earlier rule are not part of the percentage, even with a draw
	 * that would get the percentage gateway.
	 */
	public function testEarlierRuleIsUsedBeforePercentageRule(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInFR = [ 'country' => 'FR', 'currency' => 'EUR', 'payment_method' => 'cc' ];
		$seedThatDrawsSeven = 18;

		// A card donor in FR matches the first rule, and the router stops at the first rule
		// that has a supported gateway. So the card rule with the 25% entry is never checked,
		// although a draw of 7 would have chosen the percentage gateway there.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway', 'country_gateway' ], $cardDonorInFR, new NullLogger(), $seedThatDrawsSeven
		);

		$this->assertSame( 'country_gateway', $chosenGateway );
	}

	/**
	 * When the percentage gateway does not support the donor's country, a donor with a
	 * draw that would get it goes on to the next gateway in the list.
	 */
	public function testUnsupportedPercentageGatewayGoesToNextGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDrawsSeven = 18;

		// The card rule is used and the draw is 7, so the 25% entry is not skipped by the draw.
		// But percentage_gateway is not in the supported list, so the router goes on to the
		// next entry, fallback_gateway, which is supported.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'country_gateway', 'fallback_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDrawsSeven
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * Payment methods with no rule of their own use the default rule, which has no percentages.
	 */
	public function testOtherPaymentMethodsUseDefaultRule(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$bankTransferDonorInNL = [ 'country' => 'NL', 'currency' => 'EUR', 'payment_method' => 'rtbt' ];
		$seedThatDrawsSeven = 18;

		// rtbt does not match either card rule, so the default rule is used. Its first
		// supported gateway is fallback_gateway. If no rule matched at all, the router would
		// return the first supported gateway, percentage_gateway, so this result also shows
		// that the default rule was used.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'percentage_gateway', 'fallback_gateway' ], $bankTransferDonorInNL, new NullLogger(), $seedThatDrawsSeven
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * Over many sessions, close to 25% of donors get the percentage gateway.
	 * The seeds 1 to 10,000 give 2518 donors, which is 25.18%.
	 */
	public function testAbout25PercentOfDonorsGetPercentageGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];

		// Each seed stands for a different session. Each draw is equally likely to be any
		// number from 1 to 100, so about 25 in 100 draws are 25 or less. The count is not
		// exactly 2500, but it is always 2518, because the seeds are always the same.
		$percentageGatewayDonorCount = 0;
		for ( $seed = 1; $seed <= 10000; $seed++ ) {
			$chosenGateway = $router->chooseGatewayByPriority(
				[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seed
			);
			if ( $chosenGateway === 'percentage_gateway' ) {
				$percentageGatewayDonorCount++;
			}
		}

		$this->assertSame( 2518, $percentageGatewayDonorCount );
	}

	/**
	 * Over many sessions, close to half of card donors get a gateway at 50%, and the
	 * other half get the next gateway in the list.
	 * The seeds 1 to 1,000 give 518 donors to the 50% gateway, which is 51.8%.
	 */
	public function testAboutHalfOfCardDonorsGetFiftyPercentGateway(): void {
		$router = $this->getRouterWithRules( [
			[
				'conditions' => [
					'payment_method' => 'cc',
				],
				'gateways' => [
					[ 'gateway' => 'percentage_gateway', 'percentage' => 50 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];

		// The donor pays by card, so the rule with the 50% entry is used. Each seed stands
		// for a different session. Draws 1 to 50 choose percentage_gateway and draws 51 to
		// 100 skip it, so fallback_gateway gets the other donors.
		$donorCounts = [ 'percentage_gateway' => 0, 'fallback_gateway' => 0 ];
		for ( $seed = 1; $seed <= 1000; $seed++ ) {
			$chosenGateway = $router->chooseGatewayByPriority(
				[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seed
			);
			$donorCounts[$chosenGateway]++;
		}

		$this->assertSame( [ 'percentage_gateway' => 518, 'fallback_gateway' => 482 ], $donorCounts );
	}

	/**
	 * A gateway at 0% is never chosen, even with the lowest possible draw.
	 */
	public function testZeroPercentNeverChoosesTheGateway(): void {
		$router = $this->getRouterWithRules( [
			[
				'gateways' => [
					[ 'gateway' => 'percentage_gateway', 'percentage' => 0 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDrawsOne = 111;

		// 1 is the lowest possible draw, and it is still more than 0, so the 0% entry is
		// skipped. Every other draw is higher, so a 0% entry is never chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDrawsOne
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * A gateway at 100% is always chosen, even with the highest possible draw.
	 */
	public function testHundredPercentAlwaysChoosesTheGateway(): void {
		$router = $this->getRouterWithRules( [
			[
				'gateways' => [
					[ 'gateway' => 'percentage_gateway', 'percentage' => 100 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDraws100 = 68;

		// 100 is the highest possible draw, and it is not more than 100, so the 100% entry
		// is chosen. Every other draw is lower, so a 100% entry is always chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDraws100
		);

		$this->assertSame( 'percentage_gateway', $chosenGateway );
	}

	/**
	 * A draw equal to the percentage chooses the gateway.
	 */
	public function testDrawEqualToPercentageChoosesTheGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDraws25 = 105;

		// An entry is only skipped when the draw is more than the percentage. The draw is
		// exactly 25, so the 25% entry is chosen. Draws 1 to 25 make exactly 25 in 100.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDraws25
		);

		$this->assertSame( 'percentage_gateway', $chosenGateway );
	}

	/**
	 * A draw one above the percentage skips the gateway.
	 */
	public function testDrawOneAbovePercentageSkipsTheGateway(): void {
		$router = $this->getRouterWithRules( $this->getExampleRules() );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDraws26 = 145;

		// The draw is 26, which is more than 25, so the 25% entry is skipped and the next
		// entry, fallback_gateway, is chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDraws26
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * Each percentage entry uses the next draw. Here the first entry gets the first draw
	 * (87, so it is skipped) and the second entry gets the second draw (49, so it is chosen).
	 * This is why the second 50% is a share of what is left: 25% of all donors.
	 */
	public function testSecondPercentageEntryUsesTheSecondDraw(): void {
		$router = $this->getRouterWithRules( [
			[
				'gateways' => [
					[ 'gateway' => 'first_percentage_gateway', 'percentage' => 50 ],
					[ 'gateway' => 'second_percentage_gateway', 'percentage' => 50 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$supportedGateways = [ 'fallback_gateway', 'first_percentage_gateway', 'second_percentage_gateway' ];
		$seedThatDraws87Then49 = 3;

		// First entry: the draw is 87, which is more than 50, so it is skipped.
		// Second entry: the next draw is 49, which is not more than 50, so it is chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			$supportedGateways, $cardDonorInUS, new NullLogger(), $seedThatDraws87Then49
		);

		$this->assertSame( 'second_percentage_gateway', $chosenGateway );
	}

	/**
	 * When every percentage entry is skipped, the plain entry after them is chosen.
	 */
	public function testPlainEntryIsChosenWhenAllPercentageEntriesAreSkipped(): void {
		$router = $this->getRouterWithRules( [
			[
				'gateways' => [
					[ 'gateway' => 'first_percentage_gateway', 'percentage' => 50 ],
					[ 'gateway' => 'second_percentage_gateway', 'percentage' => 50 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$supportedGateways = [ 'fallback_gateway', 'first_percentage_gateway', 'second_percentage_gateway' ];
		$seedThatDraws71Then89 = 9;

		// First entry: the draw is 71, which is more than 50, so it is skipped.
		// Second entry: the next draw is 89, which is more than 50, so it is skipped.
		// fallback_gateway has no percentage, so it is chosen without a draw.
		$chosenGateway = $router->chooseGatewayByPriority(
			$supportedGateways, $cardDonorInUS, new NullLogger(), $seedThatDraws71Then89
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * When a rule has only percentage entries and they are all skipped, the next rule is used.
	 */
	public function testRuleWithOnlyPercentageEntriesFallsThroughToNextRule(): void {
		$router = $this->getRouterWithRules( [
			[
				'conditions' => [ 'payment_method' => 'cc' ],
				'gateways' => [
					[ 'gateway' => 'percentage_gateway', 'percentage' => 25 ],
				],
			],
			[
				'gateways' => [ 'unsupported_gateway', 'fallback_gateway', 'percentage_gateway' ],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$seedThatDraws46 = 1;

		// The card rule matches, but the draw is 46, which is more than 25, so its only
		// entry is skipped. The rule has nothing left, so the router goes on to the next
		// rule. There, unsupported_gateway is not in the supported list, so fallback_gateway
		// is chosen. percentage_gateway is later in that list, so it is not reached.
		$chosenGateway = $router->chooseGatewayByPriority(
			[ 'fallback_gateway', 'percentage_gateway' ], $cardDonorInUS, new NullLogger(), $seedThatDraws46
		);

		$this->assertSame( 'fallback_gateway', $chosenGateway );
	}

	/**
	 * Without a seed the draw is random, but 0% and 100% still give a known result.
	 */
	public function testPercentageAppliesWithoutASeed(): void {
		$router = $this->getRouterWithRules( [
			[
				'gateways' => [
					[ 'gateway' => 'zero_percent_gateway', 'percentage' => 0 ],
					[ 'gateway' => 'hundred_percent_gateway', 'percentage' => 100 ],
					'fallback_gateway',
				],
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'payment_method' => 'cc' ];
		$supportedGateways = [ 'fallback_gateway', 'zero_percent_gateway', 'hundred_percent_gateway' ];

		// With no seed, the router uses a random one, so the draws could be anything from
		// 1 to 100. Every draw is more than 0, so zero_percent_gateway is always skipped.
		// No draw is more than 100, so hundred_percent_gateway is always chosen.
		$chosenGateway = $router->chooseGatewayByPriority(
			$supportedGateways, $cardDonorInUS, new NullLogger()
		);

		$this->assertSame( 'hundred_percent_gateway', $chosenGateway );
	}

	/**
	 * GatewayChooser makes the percentage draw from the session ID, so a donor keeps the
	 * same gateway for the whole session. The session ID below gives the seed 2665970125,
	 * which draws 36. So a gateway at 36% is chosen, and a gateway at 35% is not.
	 * This test needs real gateways, as GatewayChooser checks which gateways support the donation.
	 */
	public function testGatewayChooserMakesTheDrawFromTheSessionId(): void {
		// Only these two gateways are enabled, and both support card donations in the US in USD
		$this->overrideConfigValues( [
			'DonationInterfaceEnableGatewayChooser' => true,
			'AdyenCheckoutGatewayEnabled' => true,
			'GravyGatewayEnabled' => true,
			'DonationInterfaceGatewayAdapters' => [
				'adyen' => 'AdyenCheckoutAdapter',
				'gravy' => 'GravyAdapter',
			],
		] );
		$cardDonorInUS = [ 'country' => 'US', 'currency' => 'USD', 'amount' => '10', 'payment_method' => 'cc' ];
		$sessionId = new SessionId( 'abcdefghijklmnopqrstuvwxyz123456' );

		// The draw is 36, which is not more than 36, so gravy is chosen
		$this->overrideConfigValue( 'DonationInterfaceGatewayPriorityRules', [
			[
				'gateways' => [
					[ 'gateway' => 'gravy', 'percentage' => 36 ],
					'adyen',
				],
			],
		] );
		$gatewayWithPercentageAt36 = $this->getGatewayChooserRedirect( $cardDonorInUS, $sessionId );

		// The draw is 36 again, which is more than 35, so gravy is skipped and adyen is chosen
		$this->overrideConfigValue( 'DonationInterfaceGatewayPriorityRules', [
			[
				'gateways' => [
					[ 'gateway' => 'gravy', 'percentage' => 35 ],
					'adyen',
				],
			],
		] );
		$gatewayWithPercentageAt35 = $this->getGatewayChooserRedirect( $cardDonorInUS, $sessionId );

		// If the seed did not come from the session ID, the draws would be random, and this
		// pair of results would only happen by chance
		$this->assertSame(
			[ 'GravyGateway', 'AdyenCheckoutGateway' ],
			[ $gatewayWithPercentageAt36, $gatewayWithPercentageAt35 ]
		);
	}

	/**
	 * Get a router that uses these priority rules.
	 */
	private function getRouterWithRules( array $rules ): GatewayRouter {
		$this->overrideConfigValue( 'DonationInterfaceGatewayPriorityRules', $rules );
		return $this->getServiceContainer()->getService( 'DonationInterface.GatewayRouter' );
	}

	/**
	 * Run GatewayChooser for these params and session, and return the special page it redirects to.
	 */
	private function getGatewayChooserRedirect( array $params, SessionId $sessionId ): string {
		$context = RequestContext::getMain();
		// A FauxRequest has no session ID of its own, so set the one GatewayChooser makes the seed from
		$request = new FauxRequest( $params, false );
		$request->setSessionId( $sessionId );
		$context->setRequest( $request );
		$context->setOutput( new OutputPage( $context ) );
		$context->setTitle( Title::newFromText( 'nonsense is apparently fine' ) );

		$router = $this->getServiceContainer()->getService( 'DonationInterface.GatewayRouter' );
		$gatewayChooser = new GatewayChooser( $router );
		$gatewayChooser->execute( null );
		// Read the redirect URL without sending the output, which would need the database
		$url = $gatewayChooser->getOutput()->getRedirect();

		parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
		return str_replace( 'Special:', '', $query['title'] );
	}

	/**
	 * Example rules with the usual layout: an earlier rule for some countries, a rule that
	 * sends 25% of card donors to one gateway and the rest to another, and a default rule.
	 */
	private function getExampleRules(): array {
		return [
			[
				// Card donors in FR and DE match this rule first, so they never reach the 25% entry
				'conditions' => [
					'country' => [ 'FR', 'DE' ],
					'payment_method' => 'cc',
				],
				'gateways' => [ 'country_gateway', 'fallback_gateway' ],
			],
			[
				// Other card donors: 25% get percentage_gateway, the rest get fallback_gateway
				'conditions' => [
					'payment_method' => 'cc',
				],
				'gateways' => [
					[ 'gateway' => 'percentage_gateway', 'percentage' => 25 ],
					'fallback_gateway',
				],
			],
			[
				// No conditions, so every other donation uses this rule
				'gateways' => [ 'fallback_gateway', 'percentage_gateway', 'country_gateway' ],
			],
		];
	}
}
