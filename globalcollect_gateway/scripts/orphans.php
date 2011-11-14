<?php

//TODO: Something that is not specific to anybody's install, here. 
global $IP;
if ( !isset($IP) ) {
	$IP = '/var/www/wikimedia-dev';
}
require_once( "$IP/maintenance/Maintenance.php" );

class GlobalCollectOrphanRectifier extends Maintenance {
	function execute(){
		
		$max_per_execute = 50;
		
		

		//load in and chunk the list of XML. Phase 1: From a file
		//MUST include contribution_tracking id! 
		
		//for each chunk, load it into a GC adapter, and have it parse as if it were a response.
		
		//load that response into DonationData, and run the thing that completes the transaction,
		//as if we were coming from resultSwitcher.  
		//(Note: This should only work if we're sitting at one of the designated statuses)
		
		$data = array(
			'order_id' => '1052864192',
			'i_order_id' => '1052864192',
			'city' => '',
			'state' => '',
			'zip' => '',
			'country' => 'US',
			'email' => '',
			'card_num' => '',
			
		);
		
		//we may need to unset some hooks out here. Like... recaptcha. Makes no sense.
		$adapter = new GlobalCollectAdapter(array('external_data' => $data));
		error_log("\n\n\n");
		$results = $adapter->do_transaction('Confirm_CreditCard');
	}
}



$maintClass = "GlobalCollectOrphanRectifier";
require_once( "$IP/maintenance/doMaintenance.php" );
