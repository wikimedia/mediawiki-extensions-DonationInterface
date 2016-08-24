<?php

class Gateway_Extras_ConversionLog extends Gateway_Extras {

	protected static $instance;

	/**
	 * Logs the response from a transaction
	 */
	protected function post_process() {
		// if the trxn has been outright rejected, log it
		if ( $this->gateway_adapter->getValidationAction() == 'reject' ) {
			$this->log(
				$this->gateway_adapter->getData_Unstaged_Escaped( 'contribution_tracking_id' ), 'Rejected'
			);
			return true;
		}

		$response = $this->gateway_adapter->getTransactionResponse();
		// make sure the response property has been set (signifying a transaction has been made)
		if ( !$response ) {
			return false;
		}

		$this->log(
			$this->gateway_adapter->getData_Unstaged_Escaped(
				'contribution_tracking_id'
			),
			"Gateway response: " . addslashes(
				$response->getTxnMessage()
			), '"' . addslashes( json_encode( $response->getData() ) ) . '"'
		);
		return true;
	}

	public static function onPostProcess( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableConversionLog' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'conversion log onPostProcess!';
		return self::singleton( $gateway_adapter )->post_process();
	}

	protected static function singleton( GatewayType $gateway_adapter ) {
		if ( !self::$instance ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

}
