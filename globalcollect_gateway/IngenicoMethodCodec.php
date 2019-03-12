<?php

/**
 * Convert our payment methods into Ingenico codes.
 */
class IngenicoMethodCodec implements StagingHelper {
	/**
	 * Stage: payment_product and a few minor tweaks
	 * Stages the payment product ID for GC.
	 * Not what I had in mind to begin with, but this *completely* blew up.\
	 * @inheritDoc
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$logger = DonationLoggerFactory::getLogger( $adapter );

		// FIXME: too much variable management
		if ( empty( $normalized['payment_method'] ) ) {
			$stagedData['payment_method'] = '';
			$stagedData['payment_submethod'] = '';
			return;
		}
		$payment_method = $normalized['payment_method'];
		$payment_submethod = $normalized['payment_submethod'];

		// We might support a variation of the submethod for this country.
		// TODO: Having to front-load the country in the payment submethod is pretty lame.
		// If we don't have one deliberately set...
		if ( !$payment_submethod ) {
			$trythis = $payment_method . '_' . strtolower( $normalized['country'] );
			if ( array_key_exists( $trythis, $adapter->getPaymentSubmethods() ) ) {
				$payment_submethod = $trythis;
				$stagedData['payment_submethod'] = $payment_submethod;
			}
		}

		// Lookup the payment product ID.
		if ( $payment_submethod ) {
			try {
				$submethod_data = $adapter->getPaymentSubmethodMeta( $payment_submethod );
				if ( isset( $submethod_data['paymentproductid'] ) ) {
					$stagedData['payment_product'] = $submethod_data['paymentproductid'];
				}
			}
			catch ( OutOfBoundsException $ex ) {
				// Already logged.  We don't have the heart to abort here.
			}
		} else {
			$logger->debug( "payment_submethod found to be empty. Probably okay though." );
		}

		switch ( $payment_method ) {
		case 'dd':
			$stagedData['date_collect'] = gmdate( 'Ymd' );
			$stagedData['direct_debit_text'] = 'Wikimedia Foundation';
			break;
		case 'ew':
			$stagedData['descriptor'] = 'Wikimedia Foundation/Wikipedia';
			break;
		}

		// Tweak transaction type
		switch ( $payment_submethod ) {
		case 'dd_nl':
			$stagedData['transaction_type'] = '01';
			break;
		case 'dd_gb':
			$stagedData['transaction_type'] = '01';
			break;
		}
	}
}
