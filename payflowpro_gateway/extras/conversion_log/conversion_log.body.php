<?php
class PayflowProGateway_Extras_ConversionLog extends PayflowProGateway_Extras {
	static $instance;

	/**
	 * Logs the response from a payflow transaction
	 */
	public function post_process( &$pfp_gateway_object, &$data ) {
		// if the trxn has been outright rejected, log it
		if ( $pfp_gateway_object->action == 'reject' ) {
			$this->log(
				$data[ 'contribution_tracking_id' ],
				'Rejected'
			);
			return TRUE;
		}

		//make sure the payflow response property has been set (signifying a transaction has been made)
		if ( !$pfp_gateway_object->payflow_response ) return FALSE;

		$this->log( 
			$data[ 'contribution_tracking_id' ], 
			"Payflow response: " . addslashes( $pfp_gateway_object->payflow_response[ 'RESPMSG' ] ), 
			'"' . addslashes( json_encode( $pfp_gateway_object->payflow_response )) . '"'
		);
		return TRUE;
	}

	static function onPostProcess( &$pfp_gateway_object, &$data ) {
		return self::singleton()->post_process( $pfp_gateway_object, $data );
	}

	static function singleton() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
}
