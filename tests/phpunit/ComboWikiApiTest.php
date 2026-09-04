<?php

use MediaWiki\Api\ApiMain;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\ComboWiki\Hooks as ComboWikiHooks;
use MediaWiki\Extension\DonationInterface\Special\ComboWiki;
use MediaWiki\Request\FauxRequest;
use SmashPig\Core\Context;

/**
 * @group Fundraising
 * @group DonationInterface
 * @group ComboWiki
 * @covers \MediaWiki\Extension\DonationInterface\ComboWiki\Hooks
 */
class ComboWikiApiTest extends DonationInterfaceApiTestCase {

	/**
	 * Donors submit from the Vue app rather than from Special:ComboWiki, so
	 * the donate request identifies itself with the 'result_page' param.
	 *
	 * NOTE: this drives the hook directly. ApiTestCase builds ApiMain itself
	 * rather than going through ApiEntryPoint, which is the only place
	 * ApiBeforeMain fires, so a doApiRequest() here would never reach it.
	 */
	public function testDonateApiTagsComboWikiDonations(): void {
		$main = $this->apiMainForParams( [ 'result_page' => ComboWiki::IDENTIFIER ] );
		( new ComboWikiHooks() )->onApiBeforeMain( $main );

		$this->assertSame( ComboWiki::IDENTIFIER, Context::get()->getSourceType() );
	}

	/**
	 * Donations from the payments-wiki forms keep the source type they have
	 * always had.
	 */
	public function testDonateApiLeavesOtherDonationsAlone(): void {
		$main = $this->apiMainForParams( [] );
		( new ComboWikiHooks() )->onApiBeforeMain( $main );

		$this->assertSame( 'payments', Context::get()->getSourceType() );
	}

	private function apiMainForParams( array $params ): ApiMain {
		$context = new RequestContext();
		$context->setRequest( new FauxRequest( $params, true ) );
		$main = new ApiMain( $context );
		return $main;
	}
}
