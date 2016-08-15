<?php

class IngenicoLanguage extends DonorLanguage {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		parent::stage( $adapter, $normalized, $stagedData );

		if ( !isset( $stagedData['language'] ) ) {
			return;
		}

		// Handle GC's mutant Chinese code.
		if ( $stagedData['language'] === 'zh' ) {
			$stagedData['language'] = 'sc';
		}
	}
}
