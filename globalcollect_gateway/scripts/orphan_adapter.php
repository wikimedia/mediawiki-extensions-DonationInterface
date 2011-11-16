<?php

class GlobalCollectOrphanAdapter extends GlobalCollectAdapter {

	public function unstage_data( $data = array(), $final = true ){
		$unstaged = array();
		foreach ( $data as $key=>$val ){
			if (is_array($val)){
				$unstaged += $this->unstage_data( $val, false );
			} else {
				if (array_key_exists($key, $this->var_map)){
					//run the unstage data functions. 
					$unstaged[$this->var_map[$key]] = $val;
					//this would be EXTREMELY bad to put in the regular adapter. 
					$this->staged_data[$this->var_map[$key]] = $val;
				} else {
					//$unstaged[$key] = $val;
				}
			}
		}
		if ($final){
			$this->stageData('response');
		}
		foreach ($unstaged as $key=>$val){
			$unstaged[$key] = $this->staged_data[$key];
		}
		return $unstaged;
	}
	
	public function loadDataAndReInit( $data ){
		$this->batch = true; //or the hooks will accumulate badness. 
		
		$this->dataObj = new DonationData( get_called_class(), false, $data );

		$this->raw_data = $this->dataObj->getData();
		
		//this would be VERY BAD anywhere else. 
		$this->raw_data['order_id'] = $this->raw_data['i_order_id'];
		$this->staged_data = $this->raw_data;
		
		$this->transaction_results = array();
		
		$this->setPostDefaults();
		$this->defineTransactions();
		$this->defineErrorMap();
		$this->defineVarMap();
		$this->defineDataConstraints();
		$this->defineAccountInfo();
		$this->defineReturnValueMap();

		$this->stageData();
	}
	
	public function addData($dataArray){
		$order_id = $this->raw_data['i_order_id'];
		parent::addData($dataArray);
		$this->raw_data['order_id'] = $order_id;
		$this->raw_data['i_order_id'] = $order_id;
		$this->staged_data['order_id'] = $order_id;
		$this->staged_data['i_order_id'] = $order_id;
	}
	
	public function do_transaction($transaction){
		switch ($transaction){
			case 'SET_PAYMENT':
			case 'CANCEL_PAYMENT':
				self::log($this->getData_Raw('contribution_tracking_id') . ": CVV: " . $this->getData_Raw('cvv_result') . ": AVS: " . $this->getData_Raw('avs_result'));
				//and then go on, unless you're testing, in which case:
				//return "NOPE";
				//break;
			default:
				return parent::do_transaction($transaction);
				break;
		}
	}
	
	public static function log( $msg, $log_level = LOG_INFO, $nothing = null ) {
		$identifier = 'orphans:' . self::getIdentifier() . "_gateway_trxn";

		// if we're not using the syslog facility, use wfDebugLog
		if ( !self::getGlobal( 'UseSyslog' ) ) {
			wfDebugLog( $identifier, $msg );
			return;
		}

		// otherwise, use syslogging
		openlog( $identifier, LOG_ODELAY, LOG_SYSLOG );
		syslog( $log_level, $msg );
		closelog();
	}
	
}