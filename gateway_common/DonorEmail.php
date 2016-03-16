<?php

class DonorEmail implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( empty( $stagedData['email'] ) ) {
			$stagedData['email'] = $adapter->getGlobal( 'DefaultEmail' );
		}
	}

	// No-op
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {}
}
