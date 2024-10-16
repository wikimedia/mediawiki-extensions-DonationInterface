<?php

use SmashPig\Core\ValidationError;

class PostalCodeValidation implements ValidationHelper {

	/**
	 * Checks that the postal code field data is valid
	 *
	 * @param GatewayType $adapter
	 * @param array $normalized Donation data in normalized form.
	 * @param ErrorState &$errors Reference to error array
	 */
	public function validate( GatewayType $adapter, $normalized, &$errors ) {
		global $wgDonationInterfaceDlocalPostalCodeLengthRequirements;
		$country = $normalized['country'] ?? '';
		$rules = [];
		$postal_code = $normalized['postal_code'] ?? '';

		if ( trim( $country ) != '' ) {
			$country = strtoupper( $country );
			if ( isset( $wgDonationInterfaceDlocalPostalCodeLengthRequirements[ $country ] ) ) {
				$rules = $wgDonationInterfaceDlocalPostalCodeLengthRequirements[ $country ];
			}
		}

		if ( trim( $postal_code ) != '' && count( $rules ) != 0 ) {
			$len = strlen( $postal_code );

			if ( $len < $rules['min'] || $len > $rules['max'] ) {
				$errors->addError( new ValidationError(
					'postal_code',
					$rules['message']
				) );
			}
		}
	}
}
