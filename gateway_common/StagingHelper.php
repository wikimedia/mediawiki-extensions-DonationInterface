<?php

interface StagingHelper {
	function stage( GatewayType $adapter, $unstagedData, &$stagedData );
	function unstage( GatewayType $adapter, $stagedData, &$unstagedData );
}
