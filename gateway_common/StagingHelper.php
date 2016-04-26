<?php

/**
 * Used to mark any class which implements an staging method, for transforming
 * data into the form expected by a payment processing gateway API endpoint.
 */
interface StagingHelper {
	/**
	 * Transform a subset of normalized data into the "staged" data expected by
	 * a payment processor.
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param array $stagedData Reference to output data.
	 */
	function stage( GatewayType $adapter, $normalized, &$stagedData );
}
