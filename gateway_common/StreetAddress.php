<?php

class StreetAddress implements StagingHelper, UnstagingHelper {

	// The zero is intentional. @see stage_street function comment.
	const STREET_ADDRESS_PLACEHOLDER = 'N0NE PROVIDED';
	const POSTAL_CODE_PLACEHOLDER = '0';

	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$stagedData['street_address'] = $this->stage_street( $normalized );
		$stagedData['postal_code'] = $this->stage_postal_code( $normalized );
	}

	/**
	 * Stage the street address
	 *
	 * In the event that there isn't anything in there, we need to send
	 * something along so that AVS checks get triggered at all.
	 *
	 * The zero is intentional: Allegedly, Some banks won't perform the check
	 * if the address line contains no numerical data.
	 * @param array $normalized data from gateway adapter
	 * @return string
	 */
	protected function stage_street( $normalized ) {
		$street = '';
		if ( isset( $normalized['street_address'] ) ) {
			$street = trim( $normalized['street_address'] );
		}

		if ( !$street
			|| !DataValidator::validate_not_just_punctuation( $street )
		) {
			$street = self::STREET_ADDRESS_PLACEHOLDER;
		}
		return $street;
	}

	/**
	 * Stage the zip / postal code
	 *
	 * In the event that there isn't anything in there, we need to send
	 * something along so that AVS checks get triggered at all.
	 * @param array $normalized all data from gateway adapter
	 * @return string
	 */
	protected function stage_postal_code( $normalized ) {
		$postalCode = '';
		if ( isset( $normalized['postal_code'] ) ) {
			$postalCode = trim( $normalized['postal_code'] );
		}
		if ( strlen( $postalCode ) === 0 ) {
			// it would be nice to check for more here, but the world has some
			// straaaange postal codes...
			$postalCode = self::POSTAL_CODE_PLACEHOLDER;
		}

		// country-based postal_code grooming to make AVS (marginally) happy
		if ( !empty( $normalized['country'] ) ) {
			switch ( $normalized['country'] ) {
			case 'CA':
				// Canada goes "A0A 0A0"... walk like an Egyptian
				$postalCode = strtoupper( $postalCode );
				// In the event that they only forgot the space, help 'em out.
				$regex = '/[A-Z]\d[A-Z]\d[A-Z]\d/';
				if ( strlen( $postalCode ) === 6
					&& preg_match( $regex, $postalCode )
				) {
					$postalCode = substr( $postalCode, 0, 3 ) . ' ' . substr( $postalCode, 3, 3 );
				}
				break;
			}
		}

		return $postalCode;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$normalized ) {
		if (
			isset( $stagedData['street_address'] ) &&
			$stagedData['street_address'] === self::STREET_ADDRESS_PLACEHOLDER
		) {
			$normalized['street_address'] = '';
		}
		if (
			isset( $stagedData['postal_code'] ) &&
			$stagedData['postal_code'] === self::POSTAL_CODE_PLACEHOLDER
		) {
			$normalized['postal_code'] = '';
		}
	}
}
