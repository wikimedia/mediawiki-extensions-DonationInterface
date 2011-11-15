<?php
//If you want to use this script, you will have to add the following line to LocalSettings.php:
//$wgAutoloadClasses['GlobalCollectOrphanAdapter'] = $IP . '/extensions/DonationInterface/globalcollect_gateway/scripts/orphan_adapter.php';

//TODO: Something that is not specific to anybody's install, here. 
global $IP;
if ( !isset($IP) ) {
	$IP = '/var/www/wikimedia-dev';
}
require_once( "$IP/maintenance/Maintenance.php" );

class GlobalCollectOrphanRectifier extends Maintenance {
	
	protected $killfiles = array();
	protected $order_ids = array();
	protected $max_per_execute = 5;
	
	
	function execute(){
		
		$order_ids = file('orphanlogs/order_ids.txt', FILE_SKIP_EMPTY_LINES);
		foreach ($order_ids as $key=>$val){
			$order_ids[$key] = trim($val);
		}
		foreach ($order_ids as $id){
			$this->order_ids[$id] = $id; //easier to unset this way. 
		}
		echo "Order ID count: " . count($this->order_ids) . "\n";
		
		$files = $this->getAllLogFileNames();
		foreach ($files as $file){
			$file_array = file($file, FILE_SKIP_EMPTY_LINES);
			$payments = $this->findTransactionLines($file_array);
			if (count($payments) === 0){
				$this->killfiles[] = $file;
			}
		}		
		
		$data = array(
			'wheeee' => 'yes'
//			'order_id' => '1052864192',
//			'i_order_id' => '1052864192',
//			'city' => '',
//			'state' => '',
//			'zip' => '',
//			'country' => 'US',
//			'email' => '',
//			'card_num' => '',
			
		);
		
		$class_name = 'GlobalCollectOrphanAdapter';
		
		$adapter = new $class_name(array('external_data' => $data));
		$adapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');
		$var_map = $adapter->defineVarMap();
		
		$xml = new DomDocument;
		
		foreach ($payments as $key => $payment_data){
			$xml->loadXML($payment_data['xml']);
			$parsed = $adapter->getResponseData($xml);
			$payments[$key]['parsed'] = $parsed;
			$payments[$key]['unstaged'] = $adapter->unstage_data($parsed);
			$payments[$key]['unstaged']['contribution_tracking_id'] = $payments['contribution_tracking_id'];
			$payments[$key]['unstaged']['i_order_id'] = $payments[$key]['unstaged']['order_id'];
		}
		//setCurrentTransaction
		//then load the XML into a DomDocument, and run getResponseData. 
		
		echo print_r($payments, true);

		//careful after this bit. 
		die();
		
		
		
		//we may need to unset some hooks out here. Like... recaptcha. Makes no sense.
		$adapter = new GlobalCollectAdapter(array('external_data' => $data));
		error_log("\n\n\n");
		$results = $adapter->do_transaction('Confirm_CreditCard');
		
		
		//$this->rewriteOrderIds();
		//and don't forget to kill the logs we don't care about anymore.
	}
	
	function getAllLogFileNames(){
		$files = array();
		if ($handle = opendir(dirname(__FILE__) . '/orphanlogs/')){
			while ( ($file = readdir($handle)) !== false ){
				if (trim($file, '.') != '' && $file != 'order_ids.txt'){
					$files[] = dirname(__FILE__) . '/orphanlogs/' . $file;
				}
			}
		}
		closedir($handle);
		return $files;
	}
	
	function findTransactionLines($file){
		$lines = array();
		$orders = array();
		$contrib_id_finder = array();
		foreach ($file as $line_no=>$line_data){
			if (strpos($line_data, '<XML><REQUEST><ACTION>INSERT_ORDERWITHPAYMENT') === 0){
				$lines[$line_no] = $line_data;
			} elseif (strpos($line_data, 'Raw XML Response')){
				$contrib_id_finder[] = $line_data;
			} elseif (strpos(trim($line_data), '<ORDERID>') === 0){
				$contrib_id_finder[] = trim($line_data);
			}
		}
		
		foreach ($lines as $line_no=>$line_data){
			if (count($orders) >= $this->max_per_execute){
				continue;
			}
			$pos1 = strpos($line_data, '<ORDERID>') + 9;
			$pos2 = strpos($line_data, '</ORDERID>');
			if ($pos2 > $pos1){
				$tmp = substr($line_data, $pos1, $pos2-$pos1);
				echo "$tmp\n";
				if (isset($this->order_ids[$tmp])){
					$orders[$tmp] = trim($line_data);
					unset($this->order_ids[$tmp]);
				}
			}
		}
		
		//reverse the array, so we find the last instance first.
		$contrib_id_finder = array_reverse($contrib_id_finder);
		foreach ($orders as $order_id => $xml){
			$contribution_tracking_id = '';
			$finder = array_search("<ORDERID>$order_id</ORDERID>", $contrib_id_finder);
			
			//now search forward (which is actually backward) to the "Raw XML" line, so we can get the contribution_tracking_id
			//TODO: Some kind of (in)sanity check for this. Just because we've found it one step backward doesn't mean...
			//...but it's kind of good. For now. 
			$explode_me = false;
			while (!$explode_me){
				++$finder;
				if (strpos($contrib_id_finder[$finder], "Raw XML Response")){
					$explode_me = $contrib_id_finder[$finder];
				}
			}
			if (strlen($explode_me)){
				$explode_me = explode(': ', $explode_me);
				$contribution_tracking_id = trim($explode_me[1]);
				$orders[$order_id] = array(
					'xml' => $xml,
					'contribution_tracking_id' => $contribution_tracking_id,
				);
			}
		}
		
		return $orders;
	}
	
	function rewriteOrderIds() {
		$file = fopen('orphanlogs/order_ids.txt', 'w');
		$outstanding_orders = implode("\n", $this->order_ids);		
		fwrite($file, $outstanding_orders);
		fclose($file);
	}
	
}

$maintClass = "GlobalCollectOrphanRectifier";
require_once( "$IP/maintenance/doMaintenance.php" );


