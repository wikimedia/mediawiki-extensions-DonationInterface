<?php

/**
 * Convert our payment methods into Adyen allowedMethods.
 * https://docs.adyen.com/developers/payment-methods/payment-methods-overview
 */
class AdyenMethodCodec implements StagingHelper, UnstagingHelper {
	/**
	 * Stage: brandCode
	 * @inheritDoc
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( empty( $normalized['payment_method'] ) ) {
			return;
		}
		switch ( $normalized['payment_method'] ) {
			case 'cc':
				$allowedMethods = 'card';
				break;
			case 'rtbt':
				// TODO: will we ever support non-iDEAL rtbt via Adyen?
				$allowedMethods = 'ideal';
				break;
			default:
				throw new UnexpectedValueException( "Invalid Payment Method '${normalized['payment_method']}' supplied" );
		}
		$stagedData['allowed_methods'] = $allowedMethods;
	}

	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		// let's map the adyen returned 'ideal' to our rtbt payment method to fix T248712
		if ( $stagedData['payment_method'] == 'ideal' ) {
			$unstagedData['payment_method'] = 'rtbt';
		}
	}

}
