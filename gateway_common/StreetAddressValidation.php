<?php

use SmashPig\Core\ValidationError;

class StreetAddressValidation implements ValidationHelper {

	/**
	 * Checks that the Street Address field data is valid
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param ErrorState &$errors Reference to error array
	 */
	public function validate( GatewayType $adapter, $normalized, &$errors ) {
		global $wgDonationInterfaceDlocalStreetLengthRequirements;
		$country = $normalized['country'] ?? '';
		$rules = [];
		$street_address = $normalized['street_address'] ?? '';

		if ( trim( $country ) != '' ) {
			$country = strtoupper( $country );
			if ( isset( $wgDonationInterfaceDlocalStreetLengthRequirements[ $country ] ) ) {
				$rules = $wgDonationInterfaceDlocalStreetLengthRequirements[ $country ];
			}
		}

		if ( trim( $street_address ) != '' && count( $rules ) != 0 ) {
			$len = strlen( $normalized['street_address'] );

			if ( $len < $rules['min'] || $len > $rules['max'] ) {
				$errors->addError( new ValidationError(
					'street_address',
					$rules['message']
				) );
			}
		}
	}
}
