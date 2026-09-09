<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use ComboWikiGatewayResult;
use MediaWiki\Extension\DonationInterface\Special\ComboWiki;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use SmashPig\Core\Context;

/**
 * Tags every queue message sent while serving the ComboWiki donation flow.
 *
 * A donation touches the queues from up to three separate requests: the
 * ComboWiki page itself, the donate API call, and the result page a donor
 * returns to from a processor. Each is a fresh SmashPig Context, so each needs
 * the source type set, and it has to be set before the gateway adapter is
 * built, since constructing the adapter can already push a
 * contribution-tracking message.
 */
class Hooks implements SpecialPageBeforeExecuteHook, ApiBeforeMainHook {

	/**
	 * Covers the ComboWiki page and the result page donors return to.
	 *
	 * @inheritDoc
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( $special instanceof ComboWiki || $special instanceof ComboWikiGatewayResult ) {
			Context::get()->setSourceType( ComboWiki::IDENTIFIER );
		}
	}

	/**
	 * Covers the donate API call. It is the only leg that cannot identify
	 * itself from the page being served, so the Vue app flags it with
	 * 'result_page'.
	 *
	 * @inheritDoc
	 */
	public function onApiBeforeMain( &$main ) {
		if ( $main->getRequest()->getVal( 'result_page' ) === ComboWiki::IDENTIFIER ) {
			Context::get()->setSourceType( ComboWiki::IDENTIFIER );
		}
	}
}
