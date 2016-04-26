<?php

class IngenicoFinancialNumber implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		// Pad some fields with zeros, to their maximum length.
		$fields = array(
			'account_number',
			'bank_code',
			'branch_code',
		);

		foreach ( $fields as $field ) {
			if ( isset( $normalized[$field] ) ) {
				$constraints = $adapter->getDataConstraints( $field );
				if ( isset( $constraints['length'] ) ) {
					$newval = DataValidator::getZeroPaddedValue( $normalized[$field], $constraints['length'] );
					if ( $newval !== false ) {
						$stagedData[$field] = $newval;
					} else {
						// Invalid value, so blank the field.
						$stagedData[$field] = '';
					}
				}
			}
		}
	}
}
