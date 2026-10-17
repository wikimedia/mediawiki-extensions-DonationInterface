<?php

namespace MediaWiki\Extension\DonationInterface\PageTraits;

trait SeedFromSessionTrait {

	/**
	 * This trait needs a function to return a WebRequest
	 */
	abstract public function getRequest();

	/**
	 * Gets a 32-bit integer depending on the request's session ID, or mt_rand if no session
	 *
	 * @return int
	 */
	protected function getRandomSeed(): int {
		$sessionIDObject = $this->getRequest()->getSessionId();
		if ( $sessionIDObject ) {
			return hexdec( substr( md5( $sessionIDObject->getId() ), 0, 8 ) );
		}
		// If there is no session, return something reasonably random
		return mt_rand();
	}
}
