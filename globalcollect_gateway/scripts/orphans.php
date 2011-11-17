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
	protected $max_per_execute = 3;
	
	
	function execute(){
		
		$order_ids = file('orphanlogs/order_ids.txt', FILE_SKIP_EMPTY_LINES);
		foreach ($order_ids as $key=>$val){
			$order_ids[$key] = trim($val);
		}
		foreach ($order_ids as $id){
			$this->order_ids[$id] = $id; //easier to unset this way. 
		}
		$outstanding_count = count($this->order_ids);
		echo "Order ID count: " . $outstanding_count . "\n";
		
		$files = $this->getAllLogFileNames();
		$payments = array();
		foreach ($files as $file){
			if (count($payments) >= $this->max_per_execute){
				continue;
			}
			$file_array = $this->getLogfileLines( $file );
			$payments = array_merge($this->findTransactionLines($file_array), $payments);
			if (count($payments) === 0){
				$this->killfiles[] = $file;
				echo print_r($this->killfiles, true);
			}
		}		
		
		$data = array(
			'wheeee' => 'yes'			
		);
		
		$adapter = new GlobalCollectOrphanAdapter(array('external_data' => $data));
		$adapter->setCurrentTransaction('INSERT_ORDERWITHPAYMENT');
		$var_map = $adapter->defineVarMap();
		
		$xml = new DomDocument;
		
		//fields that have generated notices if they're not there. 
		$additional_fields = array(
			'card_num',
			'comment',
			'size',
			'utm_medium',
			'utm_campaign',
			'referrer',
			'mname',
			'fname2',
			'lname2',
			'street2',
			'city2',
			'state2',
			'country2',
			'zip2',			
		);
		
		
		foreach ($payments as $key => $payment_data){
			$xml->loadXML($payment_data['xml']);
			$parsed = $adapter->getResponseData($xml);
			$payments[$key]['parsed'] = $parsed;
			$payments[$key]['unstaged'] = $adapter->unstage_data($parsed);
			$payments[$key]['unstaged']['contribution_tracking_id'] = $payments[$key]['contribution_tracking_id'];
			$payments[$key]['unstaged']['i_order_id'] = $payments[$key]['unstaged']['order_id'];
			foreach ($additional_fields as $val){
				if (!array_key_exists($val, $payments[$key]['unstaged'])){
					$payments[$key]['unstaged'][$val] = null;
				}
			}
		}
		
		// ADDITIONAL: log out what you did here, to... somewhere. 
		// Preferably *before* you rewrite the Order ID file. 

		//we may need to unset some hooks out here. Like... recaptcha. Makes no sense.
		foreach($payments as $payment_data){
			$adapter->loadDataAndReInit($payment_data['unstaged']);
			$results = $adapter->do_transaction('Confirm_CreditCard');
			if ($results['status'] == true){
				$adapter->log( $payment_data['unstaged']['contribution_tracking_id'] . ": FINAL: " . $results['action']);
				unset($this->order_ids[$payment_data['unstaged']['order_id']]);
			} else {
				$adapter->log( $payment_data['unstaged']['contribution_tracking_id'] . ": ERROR: " . $results['message']);
				if (strpos($results['message'], "GET_ORDERSTATUS reports that the payment is already complete.")){
					unset($this->order_ids[$payment_data['unstaged']['order_id']]);
				}
			}
			echo $results['message'] . "\n";
		}
		
		if ($outstanding_count != count($this->order_ids)){
			$this->rewriteOrderIds();
		}
	}
	
	function getAllLogFileNames(){
		$files = array();
		if ($handle = opendir(dirname(__FILE__) . '/orphanlogs/')){
			while ( ($file = readdir($handle)) !== false ){
				if (trim($file, '.') != '' && $file != 'order_ids.txt' && $file != '.svn'){
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
		
		$order_ids = $this->order_ids;
		foreach ($lines as $line_no=>$line_data){
			if (count($orders) >= $this->max_per_execute){
				continue;
			}
			$pos1 = strpos($line_data, '<ORDERID>') + 9;
			$pos2 = strpos($line_data, '</ORDERID>');
			if ($pos2 > $pos1){
				$tmp = substr($line_data, $pos1, $pos2-$pos1);
				if (isset($order_ids[$tmp])){
					$orders[$tmp] = trim($line_data);
					unset($order_ids[$tmp]);
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
	
	function getLogfileLines( $file ){
		$array = array(); //surprise! 
		$array = file($file, FILE_SKIP_EMPTY_LINES);
		//now, check about 50 lines to make sure we're not seeing any of that #012, #015 crap.
		$checkcount = 50;
		if (count($array) < $checkcount){
			$checkcount = count($array);
		}
		$convert = false;
		for ($i=0; $i<$checkcount; ++$i){
			if( strpos($array[$i], '#012') || strpos($array[$i], '#015') ){
				$convert = true;
				break;
			}
		}
		if ($convert) {
			$array2 = array(); 
			foreach ($array as $line){
				if (strpos($line, '#012')){
					$line = str_replace('#012', "\n", $line);
				}
				if (strpos($line, '#015') ){
					$line = str_replace('#015', "\r", $line);	
				}
				$array2[] = $line;
			}
			$newfile = implode("\n", $array2);
			
			$handle = fopen($file, 'w');
			fwrite($handle, $newfile);
			fclose($handle);
			$array = file($file, FILE_SKIP_EMPTY_LINES);
		}
		
		return $array;
	}
	
}

$maintClass = "GlobalCollectOrphanRectifier";
require_once( "$IP/maintenance/doMaintenance.php" );


