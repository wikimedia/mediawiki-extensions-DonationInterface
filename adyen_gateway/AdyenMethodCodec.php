<?php

use SmashPig\PaymentProviders\Adyen\ReferenceData;

/**
 * Convert our payment methods into Adyen allowedMethods.
 * https://docs.adyen.com/developers/payment-methods/payment-methods-overview
 */
class AdyenMethodCodec implements StagingHelper, UnstagingHelper {
	/**
	 * Stage: allowedMethods
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

	/**
	 * Maps from the Adyen-side name for the specific payment instrument
	 * to our payment method and submethod. Their parameter is called
	 * brandCode on the API calls we make to them, but is called
	 * paymentMethod on the query string when they send a donor back
	 * to us and is stored as 'payment_method' in processDonorReturn.
	 *
	 * @param GatewayType $adapter
	 * @param array $stagedData
	 * @param array &$unstagedData
	 */
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {
		if ( empty( $stagedData['payment_method'] ) ) {
			return;
		}
		// Call it brandCode here to make it clear this is the Adyen-side ID
		$brandCode = $stagedData['payment_method'];
		try {
			list( $method, $submethod ) = ReferenceData::decodePaymentMethod(
				$brandCode, ''
			);
			$unstagedData['payment_method'] = $method;
			$unstagedData['payment_submethod'] = $submethod;
		} catch ( OutOfBoundsException $ex ) {
			$logger = DonationLoggerFactory::getLogger( $adapter );
			$logger->error( "Unknown Adyen paymentMethod '$brandCode'" );
		}
	}

}
