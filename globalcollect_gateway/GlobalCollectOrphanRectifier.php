<?php
/**
 * TODO: Generalize to all gateways, using hooks to implement polymorphism.
 */

use Predis\Connection\ConnectionException;

class GlobalCollectOrphanRectifier {

	protected $killfiles = array();
	protected $order_ids = array();
	protected $target_execute_time;
	protected $max_per_execute; //only really used if you're going by-file.
	protected $adapter;

	public function __construct() {
		// Have to turn this off here, until we know it's using the user's ip, and
		// not 127.0.0.1 during the batch process.  Otherwise, we'll immediately
		// lock ourselves out when processing multiple charges.
		global $wgDonationInterfaceEnableIPVelocityFilter;
		$wgDonationInterfaceEnableIPVelocityFilter = false;

		if ( !$this->getOrphanGlobal( 'enable' ) ){
			$this->info( 'Orphan cron disabled. Have a nice day!' );
			return;
		}

		$this->target_execute_time = $this->getOrphanGlobal( 'target_execute_time' );
		$this->max_per_execute = $this->getOrphanGlobal( 'max_per_execute' );

		// FIXME: Is this just to trigger batch mode?
		$data = array(
			'wheeee' => 'yes'
		);
		$this->adapter = new GlobalCollectOrphanAdapter(array('external_data' => $data));
		$this->logger = DonationLoggerFactory::getLogger( $this->adapter );
	}

	protected function keepGoing(){
		$elapsed = $this->getProcessElapsed();
		if ( $elapsed < $this->target_execute_time ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This will both return the elapsed process time, and echo something for
	 * the cronspammer.
	 * @return int elapsed time since start in seconds
	 */
	protected function getProcessElapsed(){
		$elapsed = time() - $this->start_time;
		$this->info( "Elapsed Time: {$elapsed}" );
		return $elapsed;
	}

	protected function deleteMessage( $correlation_id, $queue ) {
	    DonationQueue::instance()->delete( $correlation_id, $queue );
	}

	public function processOrphans() {
		// TODO: Make this configurable.
		// 20 minutes: this is exactly equal to something on Globalcollect's side.
		$time_buffer = 60*20;

		$queue_pool = new CyclicalArray( $this->getOrphanGlobal( 'gc_cc_limbo_queue_pool' ) );
		if ( $queue_pool->isEmpty() ) {
			// FIXME: cheesy inline default
			$queue_pool = new CyclicalArray( GlobalCollectAdapter::GC_CC_LIMBO_QUEUE );
		}

		$this->info( "Slaying orphans..." );
		$this->start_time = time();

		//I want to be clear on the problem I hope to prevent with this.  Say,
		//for instance, we pull a legit orphan, and for whatever reason, can't
		//completely rectify it.  Then, we go back and pull more... and that
		//same one is in the list again. We should stop after one try per
		//message per execute.  We should also be smart enough to not process
		//things we believe we just deleted.
		$this->handled_ids = array();

		while ( $this->keepGoing() && !$queue_pool->isEmpty() ) {
			$current_queue = $queue_pool->current();
			try {
				$message = DonationQueue::instance()->peek( $current_queue );

				if ( !$message ) {
					$this->info( "Emptied queue [{$current_queue}], removing from pool." );
					$queue_pool->dropCurrent();
					continue;
				}

				$correlation_id = 'globalcollect-' . $message['gateway_txn_id'];
				if ( array_key_exists( $correlation_id, $this->handled_ids ) ) {
					// We already did this one, keep going.  It's fine to draw
					// again from the same queue.
					DonationQueue::instance()->delete( $correlation_id, $current_queue );
					continue;
				}

				// Check the timestamp to see if it's old enough, and stop when
				// we're below the threshold.  Messages are guaranteed to pop in
				// chronological order.
				$elapsed = $this->start_time - $message['date'];
				if ( $elapsed < $time_buffer ) {
					$this->info( "Exhausted new messages in [{$current_queue}], removing from pool..." );
					$queue_pool->dropCurrent();

					continue;
				}

				// We got ourselves an orphan!
				if ( $this->rectifyOrphan( $message ) ) {
					$this->handled_ids[$correlation_id] = 'rectified';
				} else {
					$this->handled_ids[$correlation_id] = 'error';
				}

				// Throw out the message either way.
				$this->deleteMessage( $correlation_id, $current_queue );

				// Round-robin the pool before we complete the loop.
				$queue_pool->rotate();
			} catch ( ConnectionException $ex ) {
				// Drop this server, for the duration of this batch.
				$this->error( "Queue server for [$current_queue] is down! Ignoring for this run..." );
				$queue_pool->dropCurrent();
			}
		}

		//TODO: Make stats squirt out all over the place.
		$rec = 0;
		$err = 0;
		foreach( $this->handled_ids as $id=>$whathappened ){
			switch ( $whathappened ){
				case 'rectified':
					$rec += 1;
					break;
				case 'error':
					$err += 1;
					break;
			}
		}
		$final = "\nDone! Final results: \n";
		$final .= " $rec rectified orphans \n";
		$final .= " $err errored out \n";
		if ( isset( $this->adapter->orphanstats ) ){
			foreach ( $this->adapter->orphanstats as $status => $count ) {
				$final .= "\n   Status $status = $count";
			}
		}
		$final .= "\n Approximately " . $this->getProcessElapsed() . " seconds to execute.\n";
		$this->info( $final );
	}

	/**
	 * Uses the Orphan Adapter to rectify (complete the charge for) a single orphan. Returns a boolean letting the caller know if
	 * the orphan has been fully rectified or not.
	 * @param array $data Some set of orphan data.
	 * @param boolean $query_contribution_tracking A flag specifying if we should query the contribution_tracking table or not.
	 * @return boolean True if the orphan has been rectified, false if not.
	 */
	protected function rectifyOrphan( $data, $query_contribution_tracking = true ){
		$data['order_id'] = $data['gateway_txn_id'];

		$this->info( "Rectifying orphan: {$data['order_id']}" );
		$rectified = false;

		$normalized = DonationQueue::queueMessageToNormalized( $data );
		$this->adapter->loadDataAndReInit( $normalized, $query_contribution_tracking );
		$results = $this->adapter->do_transaction( 'Confirm_CreditCard' );
		$message = $results->getMessage();
		if ( $results->getCommunicationStatus() ){
			$this->info( $data['contribution_tracking_id'] . ": FINAL: " . $this->adapter->getValidationAction() );
			$rectified = true;
		} else {
			$this->info( $data['contribution_tracking_id'] . ": ERROR: " . $message );
			if ( strpos( $message, "GET_ORDERSTATUS reports that the payment is already complete." ) === 0  ){
				$rectified = true;
			}

			//handles the transactions we've cancelled ourselves... though if they got this far, that's a problem too.
			$errors = $results->getErrors();
			if ( !empty( $errors ) && array_key_exists( '1000001', $errors ) ){
				$rectified = true;
			}

			//apparently this is well-formed GlobalCollect for "iono". Get rid of it.
			if ( strpos( $message, "No processors are available." ) === 0 ){
				$rectified = true;
			}
		}

		$this->info( $message );

		return $rectified;
	}

	/**
	 * Gets the global setting for the key passed in.
	 * @param string $key
	 *
	 * FIXME: Reuse GatewayAdapter::getGlobal.
	 * @return mixed
	 */
	protected static function getOrphanGlobal( $key ){
		global $wgDonationInterfaceOrphanCron;
		if ( array_key_exists( $key, $wgDonationInterfaceOrphanCron ) ){
			return $wgDonationInterfaceOrphanCron[$key];
		} else {
			return NULL;
		}
	}

	protected function info( $msg ) {
		$this->logger->info( $msg );
		print( "{$msg}\n" );
	}

	protected function error( $msg ) {
		$this->logger->error( $msg );
		error_log( $msg );
	}
}
