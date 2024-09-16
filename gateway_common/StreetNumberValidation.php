<?php

use SmashPig\Core\ValidationError;

class StreetNumberValidation implements ValidationHelper {

	/**
	 * Checks that the Street number field data is valid
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param ErrorState &$errors Reference to error array
	 */
	public function validate( GatewayType $adapter, $normalized, &$errors ) {
		global $wgDonationInterfaceDlocalStreetNumberLengthRequirements;
		$country = $normalized['country'] ?? '';
		$rules = [];
		$street_number = $normalized['street_number'] ?? '';

		if ( trim( $country ) != '' ) {
			$country = strtoupper( $country );
			if ( isset( $wgDonationInterfaceDlocalStreetNumberLengthRequirements[ $country ] ) ) {
				$rules = $wgDonationInterfaceDlocalStreetNumberLengthRequirements[ $country ];
			}
		}

		if ( trim( $street_number ) != '' && count( $rules ) != 0 ) {
			$len = strlen( $street_number );

			if ( $len < $rules['min'] || $len > $rules['max'] ) {
				$errors->addError( new ValidationError(
					'street_number',
					$rules['message']
				) );
			}
		}
	}
}
