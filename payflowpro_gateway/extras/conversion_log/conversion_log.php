<?php
/**
 * Extra to log payflow response during post processing hook
 *
 * @fixme Class/file names should likely change to reflect change in purpose...
 *
 * To install:
 *	require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/conversion_log/conversion_log.php" )
 * In LocalSettings.php:
 *	$wgHooks["PayflowGatewayPostProcess"][] = array( new PayflowProGateway_Extras_ConversionLog, 'post_process' ); // sets this script to log some information after a transaction has been processed by PayflowPro
 */
require_once( dirname( __FILE__ ) . "/../extras.php" );
class PayflowProGateway_Extras_ConversionLog extends PayflowProGateway_Extras {
	
	/**
	 * Logs the response from a payflow transaction
	 */
	public function post_process( &$pfp_gateway_object, &$data ) {
		//make sure the payflow response property has been set (signifying a transaction has been made)
		if ( !$pfp_gateway_object->payflow_response ) return FALSE;

		$this->log( 
			$data[ 'contribution_tracking_id' ], 
			"Payflow response: " . addslashes( $pfp_gateway_object->payflow_response[ 'RESPMSG' ] ), 
			'"' . addslashes( json_encode( $pfp_gateway_object->payflow_response )) . '"'
		);
		return TRUE;
	}
}
