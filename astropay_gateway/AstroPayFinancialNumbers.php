<?php

class AstroPayFinancialNumbers implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$this->stage_donor_id( $adapter, $unstagedData, $stagedData );
		$this->stage_bank_code( $adapter, $unstagedData, $stagedData );
		$this->stage_fiscal_number( $adapter, $unstagedData, $stagedData );
	}

	/**
	 * They need a 20 char string for a customer ID - give them the first 20
	 * characters of the email address for easy lookup
	 */
	protected function stage_donor_id( GatewayType $adapter, $unstagedData, &$stagedData ) {
		// We use these to look up donations by email, so strip out the trailing
		// spam-tracking sub-address to get the email we'd see complaints from.
		$email = preg_replace( '/\+[^@]*/', '', $stagedData['email'] );
		$stagedData['donor_id'] = substr( $email, 0, 20 );
	}

	protected function stage_bank_code( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$submethod = $adapter->getPaymentSubmethod();
		if ( $submethod ) {
			$meta = $adapter->getPaymentSubmethodMeta( $submethod );
			if ( isset( $meta['bank_code'] ) ) {
				$stagedData['bank_code'] = $meta['bank_code'];
			}
		}
	}

	/**
	 * Strip any punctuation from fiscal number before submitting
	 */
	protected function stage_fiscal_number( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( !empty( $unstagedData['fiscal_number'] ) ) {
			$stagedData['fiscal_number'] = preg_replace( '/[^a-zA-Z0-9]/', '', $unstagedData['fiscal_number'] );
		}
	}
}
