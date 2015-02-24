<?php

class GlobalCollectOrphanAdapter extends GlobalCollectAdapter {
	//Data we know to be good, that we always want to re-assert after a load or an addData. 
	//so far: order_id and the utm data we pull from contribution tracking. 
	protected $hard_data = array ( );

	public function __construct() {
		$this->batch = true; //always batch if we're using this object.
		parent::__construct( $options = array ( ) );
	}

	public function unstage_data( $data = array( ), $final = true ) {
		$unstaged = array( );
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$unstaged += $this->unstage_data( $val, false );
			} else {
				if ( array_key_exists( $key, $this->var_map ) ) {
					//run the unstage data functions. 
					$unstaged[$this->var_map[$key]] = $val;
					//this would be EXTREMELY bad to put in the regular adapter. 
					$this->staged_data[$this->var_map[$key]] = $val;
				} else {
					//$unstaged[$key] = $val;
				}
			}
		}
		if ( $final ) {
			$this->stageData( 'response' );
		}
		foreach ( $unstaged as $key => $val ) {
			$unstaged[$key] = $this->staged_data[$key];
		}
		return $unstaged;
	}

	public function loadDataAndReInit( $data, $useDB = true ) {
		//re-init all these arrays, because this is a batch thing.
		$this->session_killAllEverything(); // just to be sure
		$this->setTransactionResult();
		$this->hard_data = array( );
		$this->unstaged_data = array( );
		$this->staged_data = array( );

		$this->hard_data['order_id'] = $data['order_id'];

		$this->dataObj = new DonationData( $this, $data );

		$this->unstaged_data = $this->dataObj->getDataEscaped();

		if ( $useDB ){
			$this->hard_data = array_merge( $this->hard_data, $this->getUTMInfoFromDB() );
		} else {
			$utm_keys = array(
				'utm_source',
				'utm_campaign',
				'utm_medium',
				'date'
			);
			foreach($utm_keys as $key){
				$this->hard_data[$key] = $data[$key];
			}			
		}
		$this->reAddHardData();

		$this->staged_data = $this->unstaged_data;

		$this->defineTransactions();
		$this->defineErrorMap();
		$this->defineVarMap();
		$this->defineAccountInfo();
		$this->defineReturnValueMap();

		$this->stageData();

		//have to do this again here. 
		$this->reAddHardData();
		
		$this->revalidate();
	}

	public function addRequestData( $dataArray ) {
		parent::addRequestData( $dataArray );
		$this->reAddHardData();
	}

	private function reAddHardData() {
		//anywhere else, and this would constitute abuse of the system.
		//so don't do it. 
		$data = $this->hard_data;

		if ( array_key_exists( 'order_id', $data ) ) {
			$this->normalizeOrderID( $data['order_id'] );
		}
		foreach ( $data as $key => $val ) {
			$this->unstaged_data[$key] = $val;
			$this->staged_data[$key] = $val;
		}
	}

	/**
	 * Unfortunate, but we have to overload this here, or change the way we
	 * build that identifier.
	 * @param string $msg
	 * @param type $log_level
	 * @param type $nothing
	 * @return type
	 */
	public function log( $msg, $log_level = LOG_INFO, $nothing = null ) {
		$identifier = 'orphans:' . self::getIdentifier() . "_gateway_trxn";

		$msg = $this->getLogMessagePrefix() . $msg;

		// if we're not using the syslog facility, use wfDebugLog
		if ( !self::getGlobal( 'UseSyslog' ) ) {
			WmfFramework::debugLog( $identifier, $msg );
			return;
		}

		// otherwise, use syslogging
		openlog( $identifier, LOG_ODELAY, LOG_SYSLOG );
		syslog( $log_level, $msg );
		closelog();
	}

	public function getUTMInfoFromDB() {
		$db = ContributionTrackingProcessor::contributionTrackingConnection();

		if ( !$db ) {
			die( "There is something terribly wrong with your Contribution Tracking database. fixit." );
		}

		$ctid = $this->getData_Unstaged_Escaped( 'contribution_tracking_id' );

		$data = array( );

		// if contrib tracking id is not already set, we need to insert the data, otherwise update			
		if ( $ctid ) {
			$res = $db->select(
				'contribution_tracking', 
				array(
					'utm_source',
					'utm_campaign',
					'utm_medium',
					'ts'
				), 
				array( 'id' => $ctid )
			);
			foreach ( $res as $thing ) {
				$data['utm_source'] = $thing->utm_source;
				$data['utm_campaign'] = $thing->utm_campaign;
				$data['utm_medium'] = $thing->utm_medium;
				$data['ts'] = $thing->ts;
				$msg = '';
				foreach ( $data as $key => $val ) {
					$msg .= "$key = $val ";
				}
				$this->log( "$ctid: Found UTM Data. $msg" );
				echo "$msg\n";
				return $data;
			}
		}

		//if we got here, we can't find anything else...
		$this->log( "$ctid: FAILED to find UTM Source value. Using default.", LOG_ERR );
		return $data;
	}

	/**
	 * Copying this here because it's the fastest way to bring in an actual timestamp. 
	 */
	protected function doStompTransaction() {
		if ( !$this->getGlobal( 'EnableStomp' ) ) {
			return;
		}
		$this->debugarray[] = "Attempting Stomp Transaction!";
		$hook = '';

		$status = $this->getFinalStatus();
		switch ( $status ) {
			case 'complete':
				$hook = 'gwStomp';
				break;
			case 'pending':
			case 'pending-poke':
				$hook = 'gwPendingStomp';
				break;
		}
		if ( $hook === '' ) {
			$this->debugarray[] = "No Stomp Hook Found for FINAL_STATUS $status";
			return;
		}

		if ( !is_null( $this->getData_Unstaged_Escaped( 'date' ) ) ) {
			$timestamp = $this->getData_Unstaged_Escaped( 'date' );
		} else {
			if ( !is_null( $this->getData_Unstaged_Escaped( 'ts' ) ) ) {
				$timestamp = strtotime( $this->getData_Unstaged_Escaped( 'ts' ) ); //I hate that this works.
			} else {
				$timestamp = time();
			}
		}

		// send the thing.
		$transaction = array(
			'response' => $this->getTransactionMessage(),
			'date' => $timestamp,
			'gateway_txn_id' => $this->getTransactionGatewayTxnID(),
			//'language' => '',
		);
		$transaction += $this->getData_Unstaged_Escaped();

		try {
			WmfFramework::runHooks( $hook, array( $transaction ) );
		} catch ( Exception $e ) {
			$this->log( "STOMP ERROR. Could not add message. " . $e->getMessage(), LOG_CRIT );
		}
	}

}
