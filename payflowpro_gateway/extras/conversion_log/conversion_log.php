<?php
/**
 * Extra to log payflow conversion during post processing hook
 *
 * To install:
 *	require_once( "$IP/extensions/DonationInterface/payflowpro_gateway/extras/conversion_log/conversion_log.php" )
 * In LocalSettings.php:
 *	$wgHooks["PayflowGatewayPostProcess"][] = array( new PayflowProGateway_Extras_ConversionLog, 'post_process' ); // sets this script to log some information after a transaction has been processed by PayflowPro
 */
require_once( dirname( __FILE__ ) . "/../extras.php" );
class PayflowProGateway_Extras_ConversionLog extends PayflowProGateway_Extras {
	
	/**
	 * Logs the transaction info for a conversion for payflow
	 */
	public function post_process( &$pfp_gateway_object, &$data ) {
		//make sure the transaction property has been set (signifying conversion)
		if ( !$pfp_gateway_object->payflow_transaction ) return FALSE;

		$this->log( 
			$data[ 'contribution_tracking_id' ], 
			'Conversion', 
			'"' . $pfp_gateway_object->payflow_transaction[ 'PNREF' ] . '"'
		);
		return TRUE;
	}
}
