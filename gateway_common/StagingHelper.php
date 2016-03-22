<?php

/**
 * Used to mark any class which implements an staging method, for transforming
 * data into the form expected by a payment processing gateway API endpoint.
 */
interface StagingHelper {
	function stage( GatewayType $adapter, $unstagedData, &$stagedData );
}
