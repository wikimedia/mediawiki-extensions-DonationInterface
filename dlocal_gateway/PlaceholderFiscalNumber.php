<?php

/**
 * Not validated, but currently required by dLocal APIs. Format varies
 * by country. Needs to be random so they don't block a repeated one.
 * TODO: Remove this when they fix it
 */
class PlaceholderFiscalNumber implements StagingHelper {
	protected $placeholders = [
		'MX' => [ 1.0e+12, 1.0e+13 ],
		'PE' => [ 1.0e+8, 1.0e+10 ],
		'IN' => 'AABBC1122C', // DLOCAL-specific PAN. See T258086
		'ZA' => '9123456783'  // DLOCAL-specific default for empty cpf. See T307743
	];

	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if (
			empty( $unstagedData['fiscal_number'] ) &&
			isset( $unstagedData['country'] ) &&
			array_key_exists( $unstagedData['country'], $this->placeholders )
		) {
			$country = $unstagedData['country'];
			$fiscalNumber = $this->placeholders[$country];

			// if placeholder is an array we use the values as upper and lower range bounds
			if ( is_array( $fiscalNumber ) ) {
				$lower = $fiscalNumber[0];
				$upper = $fiscalNumber[1];
				$fiscalNumber = mt_rand( $lower, $upper );
			}

			$stagedData['fiscal_number'] = $fiscalNumber;
		}
	}
}
