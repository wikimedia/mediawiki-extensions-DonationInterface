<?php

class DonorEmail implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $stagedData['email'] ) ) {
			$stagedData['email'] = $adapter->getGlobal( 'DefaultEmail' );
		}
	}
}
