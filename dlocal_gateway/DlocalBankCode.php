<?php

class DlocalBankCode implements StagingHelper {
	/**
	 * @param GatewayType $adapter
	 * @param array $normalized
	 * @param array &$stagedData
	 * @return void
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ): void {
		$this->stage_bank_code( $adapter, $stagedData );
	}

	/**
	 * At the moment we're only staging the bank_code for non-cc payments. The bank_code is
	 * mapped to the dlocal-specific payment_method_id which indicates the specific payment method we want.
	 *
	 * @param GatewayType $adapter
	 * @param array &$stagedData
	 * @return void
	 */
	protected function stage_bank_code( GatewayType $adapter, array &$stagedData ): void {
		$payment_method = $adapter->getPaymentMethod();
		$submethod = $adapter->getPaymentSubmethod();

		if ( $this->isCashOrBankPaymentMethod( $payment_method ) && $submethod ) {
			$meta = $adapter->getPaymentSubmethodMeta( $submethod );
			if ( isset( $meta['bank_code'] ) ) {
				$stagedData['bank_code'] = $meta['bank_code'];
			}
		}
	}

	/**
	 * @param string $payment_method
	 * @return bool
	 */
	protected function isCashOrBankPaymentMethod( string $payment_method ): bool {
		return $payment_method === 'cash' || $payment_method === 'bt';
	}

}
