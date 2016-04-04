<?php

/**
 * Strip any punctuation from fiscal number before submitting
 */
class FiscalNumber implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !empty( $normalized['fiscal_number'] ) ) {
			$stagedData['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $normalized['fiscal_number'] );
		}
	}
}
