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
}