<?php

class AstroPayFinancialNumbers implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$this->stage_donor_id( $adapter, $normalized, $stagedData );
		$this->stage_bank_code( $adapter, $normalized, $stagedData );
	}

	/**
	 * They need a 20 char string for a customer ID - give them the first 20
	 * characters of the email address for easy lookup
	 * @inheritDoc
	 */
	protected function stage_donor_id( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( !isset( $stagedData['email'] ) ) {
			return;
		}
		// We use these to look up donations by email, so strip out the trailing
		// spam-tracking sub-address to get the email we'd see complaints from.
		$email = preg_replace( '/\+[^@]*/', '', $stagedData['email'] );
		$stagedData['donor_id'] = substr( $email, 0, 20 );
	}

	protected function stage_bank_code( GatewayType $adapter, $normalized, &$stagedData ) {
		$submethod = $adapter->getPaymentSubmethod();
		if ( $submethod ) {
			$meta = $adapter->getPaymentSubmethodMeta( $submethod );
			if ( isset( $meta['bank_code'] ) ) {
				$stagedData['bank_code'] = $meta['bank_code'];
			}
		}
	}
}
