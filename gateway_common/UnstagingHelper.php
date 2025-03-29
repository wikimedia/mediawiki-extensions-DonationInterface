<?php

/**
 * Used to mark any class which implements an unstaging method, for transforming
 * data returned by a payment processing gateway API call.
 */
interface UnstagingHelper {
	/**
	 * @param GatewayType $adapter
	 * @param array $stagedData
	 * @param array &$unstagedData
	 */
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData );
}
