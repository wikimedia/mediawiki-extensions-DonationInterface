<?php

class AstroPaySignature implements StagingHelper {
	/**
	 * Sign an AstroPay NewInvoice request
	 * TODO: switch on transaction, build correct message for refund
	 * @param GatewayType $adapter
	 * @param array $normalized
	 * @param array &$stagedData
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$message = self::getNewInvoiceMessage( $stagedData );
		$stagedData['control'] = self::calculateSignature( $adapter, $message );
	}

	public static function getNewInvoiceMessage( $stagedData ) {
		$requiredKeys = [
			'order_id', 'amount', 'donor_id', 'bank_code', 'fiscal_number', 'email'
		];
		$stagedKeys = array_keys( $stagedData );
		if ( array_intersect( $requiredKeys, $stagedKeys ) != $requiredKeys ) {
			return '';
		} else {
			// Set city to null when not needed
			$city = $stagedData['city'] ?? '';

			return str_replace( '+', ' ',
				$stagedData['order_id'] . 'V'
				. $stagedData['amount'] . 'I'
				. $stagedData['donor_id'] . '2'
				. $stagedData['bank_code'] . '1'
				. $stagedData['fiscal_number'] . 'H'
				. /* bdate omitted */ 'G'
				. $stagedData['email'] . 'Y'
				. /* postal_code omitted */ 'A'
				. $stagedData['street_address'] . 'P'
				. $city . 'S'
				. /* state_province omitted */ 'P'
			);
		}
	}

	public static function calculateSignature( GatewayType $adapter, $message ) {
		$key = $adapter->getAccountConfig( 'SecretKey' );
		return strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $message ), pack( 'A*', $key ) )
		);
	}
}
