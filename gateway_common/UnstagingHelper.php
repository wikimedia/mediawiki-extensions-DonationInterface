<?php

/**
 * Used to mark any class which implements an unstaging method, for transforming
 * data returned by a payment processing gateway API call.
 */
interface UnstagingHelper {
	function unstage( GatewayType $adapter, $stagedData, &$unstagedData );
}
