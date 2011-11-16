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
	
}