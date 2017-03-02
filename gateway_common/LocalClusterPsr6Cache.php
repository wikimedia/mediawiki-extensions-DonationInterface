<?php

use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCache;

/**
 * A PSR-6 compatible wrapper to let SmashPig objects use Mediawiki's
 * local cluster BagOStuff cache via Addshore's wrapper.
 * Suggested by Adam Wight <awight@wikimedia.org>
 * To use, add this to your SmashPig configuration under key 'cache':
 *  class: LocalClusterPsr6Cache
 * (no constructor-parameters need to be specified)
 */
class LocalClusterPsr6Cache extends BagOStuffPsrCache {
	public function __construct() {
		$mainCache = ObjectCache::getLocalClusterInstance();
		parent::__construct( $mainCache );
	}
}
