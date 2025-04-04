<?php

class BlankAddressFields implements StagingHelper, UnstagingHelper {

	/**
	 * Supply default value 'NA' for blank address fields when required
	 * as per Adyen's suggestion for passing their validation with
	 * unused fields.
	 * @var string[]
	 */
	private static $addressFields = [
		'city',
		'state_province',
		'street_address',
		'supplemental_address_1',
		'postal_code',
	];

	/** @inheritDoc */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		// If any address field is not blank, fill in blanks with 'NA'
		// if all fields are blank, leave it alone
		foreach ( self::$addressFields as $address ) {
			if ( !empty( $normalized[ $address ] ) ) {
				foreach ( self::$addressFields as $field ) {
					if ( empty( $normalized[ $field ] ) ) {
						$stagedData[ $field ] = 'NA';
					}
				}
				break;
			}
		}
	}

	/** @inheritDoc */
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		foreach ( self::$addressFields as $field ) {
			if ( isset( $stagedData[ $field ] ) && $stagedData[ $field ] == 'NA' ) {
				$unstagedData[ $field ] = '';
			}
		}
	}

}
