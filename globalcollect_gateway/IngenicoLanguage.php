<?php

class IngenicoLanguage extends DonorLanguage {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		parent::stage( $adapter, $unstagedData, $stagedData );

		// Handle GC's mutant Chinese code.
		if ( $stagedData['language'] === 'zh' ) {
			$stagedData['language'] = 'sc';
		}
	}
}
