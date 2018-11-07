<?php

/**
 * Not validated, but currently required by the AstroPay API. Needs to
 * be 13 digits for MX and 8-9 digits for PE, and random so they don't
 * blacklist a repeated one.
 * TODO: Remove this when they fix it
 */
class DummyFiscalNumber implements StagingHelper {
	protected $countriesWithWorkaround = [
		'MX' => [ 1.0e+12, 1.0e+13 ],
		'PE' => [ 1.0e+8, 1.0e+10 ],
	];

	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if (
			empty( $unstagedData['fiscal_number'] ) &&
			isset( $unstagedData['country'] ) &&
			in_array( $unstagedData['country'], array_keys( $this->countriesWithWorkaround ) )
		) {
			$lower = $this->countriesWithWorkaround[$unstagedData['country']][0];
			$upper = $this->countriesWithWorkaround[$unstagedData['country']][1];
			$stagedData['fiscal_number'] = mt_rand( $lower, $upper );
		}
	}
}
