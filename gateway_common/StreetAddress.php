<?php

class StreetAddress implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$stagedData['street'] = $this->stage_street( $unstagedData );
		$stagedData['zip'] = $this->stage_zip( $unstagedData );
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
	protected function stage_street( $unstagedData ) {
		$street = '';
		if ( isset( $unstagedData['street'] ) ) {
			$street = trim( $unstagedData['street'] );
		}

		if ( !$street
			|| !DataValidator::validate_not_just_punctuation( $street )
		) {
			$street = 'N0NE PROVIDED'; //The zero is intentional. See function comment.
		}
		return $street;
	}

	/**
	 * Stage the zip / postal code
	 *
	 * In the event that there isn't anything in there, we need to send
	 * something along so that AVS checks get triggered at all.
	 */
	protected function stage_zip( $unstagedData ) {
		$zip = '';
		if ( isset( $unstagedData['zip'] ) ) {
			$zip = trim( $unstagedData['zip'] );
		}
		if ( strlen( $zip ) === 0 ) {
			//it would be nice to check for more here, but the world has some
			//straaaange postal codes...
			$zip = '0';
		}

		//country-based zip grooming to make AVS (marginally) happy
		if ( !empty( $unstagedData['country'] ) ) {
			switch ( $unstagedData['country'] ) {
			case 'CA':
				//Canada goes "A0A 0A0"
				$this->staged_data['zip'] = strtoupper( $zip );
				//In the event that they only forgot the space, help 'em out.
				$regex = '/[A-Z]\d[A-Z]\d[A-Z]\d/';
				if ( strlen( $this->staged_data['zip'] ) === 6
					&& preg_match( $regex, $zip )
				) {
					$zip = substr( $zip, 0, 3 ) . ' ' . substr( $zip, 3, 3 );
				}
				break;
			}
		}

		return $zip;
	}
}
