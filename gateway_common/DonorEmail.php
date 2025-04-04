<?php

class DonorEmail implements StagingHelper {
	/** @inheritDoc */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $stagedData['email'] ) ) {
			$stagedData['email'] = $adapter->getGlobal( 'DefaultEmail' );
		}
	}
}
