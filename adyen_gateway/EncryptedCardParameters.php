<?php

use SmashPig\Core\ValidationError;

/**
 * Ensures that we have all the required parameters to make a credit card payment
 */
class EncryptedCardParameters implements ValidationHelper {

	/**
	 * Mapping of required parameter names to error message keys
	 *
	 * @var array|string[]
	 */
	private static array $requiredFields = [
		'encrypted_card_number' => 'donate_interface-error-msg-card-num',
		'encrypted_expiry_month' => 'donate_interface-error-msg-expiration',
		'encrypted_expiry_year' => 'donate_interface-error-msg-expiration',
		// Adyen docs say some cards don't have a security code
	];

	public function validate( GatewayType $adapter, $normalized, &$errors ) {
		// validate() is called at various points in the process. We only want to require these
		// parameters for card payments, and only during the 'donate' action (not during initial
		// form load nor during recurring upgrade). It's a bit odd to find the action in the
		// $normalized array, but there it is!
		$isCardPayment = ( isset( $normalized['payment_method'] ) && $normalized['payment_method'] === 'cc' );
		$isSubmitPhase = ( isset( $normalized['action'] ) && $normalized['action'] === 'di_donate_adyen' );
		if ( $isCardPayment && $isSubmitPhase ) {
			foreach ( self::$requiredFields as $field => $errorKey ) {
				if ( empty( $normalized[$field] ) ) {
					$errors->addError( new ValidationError( $field, $errorKey ) );
				}
			}
		}
	}
}
