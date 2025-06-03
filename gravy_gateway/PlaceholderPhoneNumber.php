<?php

/**
 * Not validated, but currently required by gravy india APIs.
 */
class PlaceholderPhoneNumber implements StagingHelper {
	/** @inheritDoc */
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if (
			empty( $unstagedData['phone'] ) &&
			( isset( $unstagedData['country'] ) && $unstagedData['country'] === 'IN' ) &&
			( isset( $unstagedData['payment_method'] ) && $unstagedData['payment_method'] === 'cc' )
		) {
			$stagedData['phone'] = '+919000123456';
		}
	}
}
