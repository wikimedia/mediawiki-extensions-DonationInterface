<?php

class FullNameWithExceptions extends DonorFullName {
	/**
	 * We're automatically filling and hiding the cardholder name box, except
	 * for Hebrew speakers, who Amir tells us enter contact name and billing
	 * name in different scripts.
	 * TODO: If anyone wants more languages on the list, make a config file
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( $normalized['language'] !== 'he' ) {
			parent::stage( $adapter, $normalized, $stagedData );
		}
	}
}
