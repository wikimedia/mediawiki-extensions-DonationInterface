<?php

class StreetAddress implements StagingHelper {
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
	 */
	protected function stage_street( $normalized ) {
		$street = '';
		if ( isset( $normalized['street_address'] ) ) {
			$street = trim( $normalized['street_address'] );
		}

		if ( !$street
			|| !DataValidator::validate_not_just_punctuation( $street )
		) {
			$street = 'N0NE PROVIDED'; // The zero is intentional. See function comment.
		}
		return $street;
	}

	/**
	 * Stage the zip / postal code
	 *
	 * In the event that there isn't anything in there, we need to send
	 * something along so that AVS checks get triggered at all.
	 */
	protected function stage_postal_code( $normalized ) {
		$postalCode = '';
		if ( isset( $normalized['postal_code'] ) ) {
			$postalCode = trim( $normalized['postal_code'] );
		}
		if ( strlen( $postalCode ) === 0 ) {
			// it would be nice to check for more here, but the world has some
			// straaaange postal codes...
			$postalCode = '0';
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
}
