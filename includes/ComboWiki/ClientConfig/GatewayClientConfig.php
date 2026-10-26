<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki\ClientConfig;

use MediaWiki\Output\OutputPage;

/**
 * What ComboWiki sends to the frontend for one enabled gateway, such as the
 * gateway SDK configuration, and what it adds to the page head for it.
 */
interface GatewayClientConfig {
	/**
	 * @return array Variables for the frontend, merged into the ComboWiki config vars
	 */
	public function getVars(): array;

	/**
	 * Adds the links and preloads the gateway needs in the page head,
	 * e.g. a stylesheet or a dns-prefetch.
	 *
	 * @param OutputPage $out
	 * @return void
	 */
	public function addResources( OutputPage $out ): void;
}
