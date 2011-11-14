<?php

//TODO: Something that is not specific to anybody's install, here. 
global $IP;
if ( !isset($IP) ) {
	$IP = '/var/www/wikimedia-dev';
}
require_once( "$IP/maintenance/Maintenance.php" );

class GlobalCollectOrphanRectifier extends Maintenance {
	function execute(){

		//load in and chunk the list of XML. Phase 1: From a file
		
		//for each chunk, load it into a GC adapter, and have it parse as if it were a response.
		
		//load that response into DonationData, and run the thing that completes the transaction,
		//as if we were coming from resultSwitcher.  
		//(Note: This should only work if we're sitting at one of the designated statuses)
		
		$data = array(
			'test' => 'something',
			'othertest' => 'otherthing'
		);
		
		$adapter = new GlobalCollectAdapter(array('external_data' => $data));
	}
}



$maintClass = "GlobalCollectOrphanRectifier";
require_once( "$IP/maintenance/doMaintenance.php" );
