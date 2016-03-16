<?php

/**
 * Strip any punctuation from fiscal number before submitting
 */
class BrazilianFiscalNumber implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( !empty( $unstagedData['fiscal_number'] ) ) {
			$stagedData['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $unstagedData['fiscal_number'] );
		}
	}
}
