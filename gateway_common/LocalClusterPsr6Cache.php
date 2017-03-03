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

	/**
	 * @var BagOStuff
	 */
	protected static $mainCache = null;

	public function __construct() {
		if ( self::$mainCache === null ) {
			self::$mainCache = ObjectCache::getLocalClusterInstance();
			if ( self::$mainCache instanceof EmptyBagOStuff ) {
				// FIXME: where does this go?
				wfLogWarning(
					'ObjectCache::getLocalClusterInstance() returned EmptyBagOStuff, using HashBagOStuff'
				);
				self::$mainCache = new HashBagOStuff();
			}
		}
		parent::__construct( self::$mainCache );
	}
}
