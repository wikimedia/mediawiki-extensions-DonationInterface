<?php

use SmashPig\PaymentData\FinalStatus;

class GlobalCollectOrphanAdapter extends GlobalCollectAdapter {
	// Data we know to be good, that we always want to re-assert after a load or an addData.
	// so far: order_id and the data we pull from contribution tracking.
	protected $hard_data = [];

	public static function getLogIdentifier() {
		return 'orphans:' . self::getIdentifier() . "_gateway_trxn";
	}

	public function __construct() {
		$this->batch = true; // always batch if we're using this object.

		// FIXME: This is just to trigger batch code paths within DonationData.
		// Do so explicitly instead.
		$options = [
			'external_data' => [
				'wheeee' => 'yes'
			],
		];

		parent::__construct( $options );
	}

	/**
	 * FIXME: Get rid of this.
	 * @param array $data
	 * @param bool $final
	 * @return array
	 */
	public function unstage_data( $data = [], $final = true ) {
		$unstaged = [];
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$unstaged += $this->unstage_data( $val, false );
			} else {
				if ( array_key_exists( $key, $this->var_map ) ) {
					// run the unstage data functions.
					$unstaged[$this->var_map[$key]] = $val;
					// this would be EXTREMELY bad to put in the regular adapter.
					$this->staged_data[$this->var_map[$key]] = $val;
				} else {
					// $unstaged[$key] = $val;
				}
			}
		}
		if ( $final ) {
			// FIXME
			$this->stageData();
		}
		foreach ( $unstaged as $key => $val ) {
			$unstaged[$key] = $this->staged_data[$key];
		}
		return $unstaged;
	}

	/**
	 * FIXME: This needs some serious code reuse trickery.
	 * @param array $data
	 */
	public function loadDataAndReInit( $data ) {
		// re-init all these arrays, because this is a batch thing.
		$this->session_killAllEverything(); // just to be sure
		$this->errorState = new ErrorState();
		$this->transaction_response = new PaymentTransactionResponse();
		$this->hard_data = [
			'order_id' => $data['order_id']
		];
		$this->unstaged_data = [];
		$this->staged_data = [];

		$this->dataObj = new DonationData( $this, $data );

		$this->unstaged_data = $this->dataObj->getData();

		$this->hard_data = array_merge( $this->hard_data, $this->getContributionTracking() );
		$this->reAddHardData();

		$this->staged_data = $this->unstaged_data;

		$this->defineTransactions();
		$this->defineErrorMap();
		$this->defineVarMap();
		$this->defineAccountInfo();
		$this->defineReturnValueMap();

		$this->stageData();

		// have to do this again here.
		$this->reAddHardData();

		$this->validate();
	}

	public function addRequestData( $dataArray ) {
		parent::addRequestData( $dataArray );
		$this->reAddHardData();
	}

	private function reAddHardData() {
		// anywhere else, and this would constitute abuse of the system.
		// so don't do it.
		$data = $this->hard_data;

		if ( array_key_exists( 'order_id', $data ) ) {
			$this->normalizeOrderID( $data['order_id'] );
		}
		foreach ( $data as $key => $val ) {
			$this->unstaged_data[$key] = $val;
			$this->staged_data[$key] = $val;
		}
	}

	public function getContributionTracking() {
		if ( $this->getData_Unstaged_Escaped( 'utm_source' ) ) {
			// We already have the info.
			return [];
		}

		$ctid = $this->getData_Unstaged_Escaped( 'contribution_tracking_id' );
		$data = [];

		if ( $ctid ) {
			// This should only ever execute under drupal/CiviCRM
			$data = db_select( 'contribution_tracking', 'contribution_tracking' )
				->fields(
					'contribution_tracking',
					[
						'contribution_id',
						'utm_source',
						'utm_campaign',
						'utm_medium',
						'ts'
					]
				)
				->condition( 'id', $ctid )
				->execute()
				->fetchAssoc();

			if ( !empty( $data ) ) {
				$this->logger->info( "$ctid: Found UTM Data. " . print_r( $data, true ) );
				return $data;
			}
		}

		// if we got here, we can't find anything else...
		$this->logger->error( "$ctid: FAILED to find contribution tracking data. Using default." );
		return $data;
	}

	/**
	 * Copy the timestamp rather than using the current time.
	 *
	 * FIXME: Carefully move this to the base class and decide when appropriate.
	 * @return array
	 */
	protected function getQueueDonationMessage() {
		$transaction = parent::getQueueDonationMessage();

		// Overwrite the time field, if historical date is available.
		if ( $this->getData_Unstaged_Escaped( 'date' ) !== null ) {
			$transaction['date'] = $this->getData_Unstaged_Escaped( 'date' );
		} elseif ( $this->getData_Unstaged_Escaped( 'ts' ) !== null ) {
			$transaction['date'] = strtotime( $this->getData_Unstaged_Escaped( 'ts' ) ); // I hate that this works. FIXME: wat.
		}

		return $transaction;
	}

	protected function setGatewayDefaults( $options = [] ) {
		// Prevent MediaWiki code paths.
		parent::setGatewayDefaults( [
			'returnTo' => '',
		] );
	}

	/**
	 * Do the antifraud checks here instead.
	 */
	protected function post_process_get_orderstatus() {
		// TODO: Let's parse this into a "not so final status" attribute.
		$status_response = $this->transaction_response->getData();
		$action = $this->findCodeAction( 'GET_ORDERSTATUS', 'STATUSID', $status_response['STATUSID'] );

		// Don't bother unless we expect to be able to finalize this payment.
		if ( $action === FinalStatus::PENDING_POKE ) {
			$this->logger->info( 'Final status is PENDING_POKE, running fraud filters' );
			parent::post_process_get_orderstatus();
		} else {
			$this->logger->info( "Skipping fraud filters for final status $action." );
		}
	}
}
