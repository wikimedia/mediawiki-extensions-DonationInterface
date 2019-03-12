<?php

/**
 * TestingGlobalCollectOrphanAdapter
 */

class TestingGlobalCollectOrphanAdapter extends GlobalCollectOrphanAdapter {
	use TTestingAdapter;

	/**
	 * Also set a useful MerchantID.
	 * @param array|null $options
	 */
	public function __construct( $options = array() ) {
		if ( is_null( $options ) ) {
			$options = array();
		}

		// I hate myself for this part, and so do you.
		// Deliberately not fixing the actual problem for this patchset.
		// @TODO: Change the way the constructor works in all adapter
		// objects, such that the mess I am about to make is no longer
		// necessary. A patchset may already be near-ready for this...
		if ( array_key_exists( 'order_id_meta', $options ) ) {
			$this->order_id_meta = $options['order_id_meta'];
			unset( $options['order_id_meta'] );
		}

		$this->options = $options;

		parent::__construct( $this->options );
	}
}
