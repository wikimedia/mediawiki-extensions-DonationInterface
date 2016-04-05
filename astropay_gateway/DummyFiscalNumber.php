<?php

/**
 * Not validated, but currently required by the AstroPay API. Needs to
 * be 13 digits, and random so they don't blacklist a repeated one.
 * TODO: Remove this when they fix it
 */
class DummyFiscalNumber implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if (
			empty( $unstagedData['fiscal_number'] ) &&
			isset( $unstagedData['country'] ) &&
			$unstagedData['country'] === 'MX'
		) {
			$stagedData['fiscal_number'] = mt_rand(1.0e+12, 1.0e+13);
		}
	}
}
