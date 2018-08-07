<?php

use SmashPig\Core\DataStores\PendingDatabase;

/**
 * Detect and rectify recently orphaned Ingenico transactions
 *
 * An "orphaned" transaction is one where we never regained control after
 * redirecting to the hosted payment page.  The three most common statuses
 * for an orphaned transaction to be in are:
 *   - Pending-poke: Waiting for API finalization.  We need to do something now.
 *   - Pending: Abandoned by the user, we should cancel or simply let this expire.
 *   - Completed: No further action is required.
 *
 * TODO: Generalize to all gateways, using hooks to implement polymorphism.
 */
class GlobalCollectOrphanRectifier {

	/**
	 * @var int Time in un*x seconds at the beginning of this job.
	 */
	protected $start_time;

	/**
	 * @var int Configured maximum job duration, in seconds.
	 * TODO: Rename to $maximum_execute_time.
	 */
	protected $target_execute_time;

	/**
	 * @var int Don't process any transactions newer than this number of seconds.
	 * Defaults to 20 minutes, which is exactly equal to [DOCUMENT] something
	 * on Globalcollect's side.  Perhaps the time it takes to get out of the
	 * pending status?
	 */
	protected $time_buffer;

	/**
	 * @var GlobalCollectOrphanAdapter Payments adapter to do the processing.
	 */
	protected $adapter;

	/**
	 * @var Psr\Log\LoggerInterface Log sink
	 */
	protected $logger;

	/**
	 * // TODO: don't do anything in the constructor.
	 */
	public function __construct() {
		// Have to turn this off here, until we know it's using the user's ip, and
		// not 127.0.0.1 during the batch process.  Otherwise, we'll immediately
		// lock ourselves out when processing multiple charges.
		global $wgDonationInterfaceEnableIPVelocityFilter;
		$wgDonationInterfaceEnableIPVelocityFilter = false;

		// Fetch configuration
		$this->target_execute_time = $this->getOrphanGlobal( 'target_execute_time' );
		$this->time_buffer = $this->getOrphanGlobal( 'time_buffer' );

		$className = DonationInterface::getAdapterClassForGateway( 'globalcollect_orphan' );
		$this->adapter = new $className();
		$this->logger = DonationLoggerFactory::getLogger( $this->adapter );
	}

	/**
	 * @return bool True until our job timer runs to zero.
	 */
	protected function keepGoing() {
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
	protected function getProcessElapsed() {
		$elapsed = time() - $this->start_time;
		$this->logger->info( "Elapsed Time: {$elapsed}" );
		return $elapsed;
	}

	/**
	 * Run in a loop until all the orphans are processed or we time out.
	 */
	public function processOrphans() {
		$num_rectified = 0;
		$num_errored = 0;

		$this->logger->info( "Slaying orphans..." );
		$this->start_time = time();

		while ( true ) {
			// Stop if we used up the target_execute_time.
			if ( !$this->keepGoing() ) {
				$this->logger->info( 'Done, timed out before finishing backlog.' );
				break;
			}

			// Get a message.
			$message = $this->getNextMessage();
			if ( !$message ) {
				$this->logger->info( 'Done, no more messages!' );
				break;
			}

			// Check the timestamp to see if this message is old enough to
			// process.  We want to give the donor a generous window in which
			// to complete the transaction normally.

			// Messages are retrieved in chronological order, so we can stop
			// grabbing messages once we hit a message newer than the grace
			// period in which a donor might still be fumbling for wallet.

			$elapsed = $this->start_time - $message['date'];
			if ( $elapsed < $this->time_buffer ) {
				$this->logger->info( 'Done, only new messages remaining.' );
				break;
			}

			// We got ourselves an orphan!
			if ( $this->rectifyOrphan( $message ) ) {
				$num_rectified++;
			} else {
				$num_errored++;
			}

			// Throw out the message either way.
			$this->deleteMessage( $message );
		}

		// TODO: Make stats squirt out all over the place.
		$final = "Final results: \n";
		$final .= " {$num_rectified} rectified orphans \n";
		$final .= " {$num_errored} errored out \n";
		// TODO: Wrap in an interface, even a helper object.
		if ( isset( $this->adapter->orphanstats ) ) {
			foreach ( $this->adapter->orphanstats as $status => $count ) {
				$final .= "\n   Status $status = $count";
			}
		}
		$final .= "\n Approximately " . $this->getProcessElapsed() . " seconds to execute.\n";
		$this->logger->info( $final );
	}

	/**
	 * Uses the Orphan Adapter to rectify (complete the charge for) a single
	 * orphan. Returns a boolean letting the caller know if the orphan has been
	 * fully rectified or not.
	 *
	 * @param array $normalized Orphaned message
	 *
	 * @return bool True if the orphan has been rectified, false if not.
	 */
	protected function rectifyOrphan( $normalized ) {
		if ( $normalized['payment_method'] !== 'cc' ) {
			// Skip other payment methods which shouldn't be in the pending
			// queue anyway.  See https://phabricator.wikimedia.org/T161160
			$this->logger->info( "Skipping non-credit card pending record." );
			return false;
		}

		$this->logger->info( "Rectifying orphan: {$normalized['order_id']}" );
		$is_rectified = false;

		$this->adapter->loadDataAndReInit( $normalized );
		$civiId = $this->adapter->getData_Unstaged_Escaped( 'contribution_id' );
		if ( $civiId ) {
			$this->logger->error(
				$normalized['contribution_tracking_id'] .
				": Contribution tracking already has contribution_id $civiId.  " .
				'Stop confusing donors!'
			);
			$results = $this->adapter->do_transaction( 'CANCEL_PAYMENT' );
		} else {
			$results = $this->adapter->do_transaction( 'Confirm_CreditCard' );
		}

		// FIXME: error message is squishy and inconsistent with the error_map
		// used elsewhere.
		$message = $results->getMessage();
		$this->logger->info( "Result message: {$message}" );

		if ( $results->getCommunicationStatus() ) {
			$this->logger->info( $normalized['contribution_tracking_id'] . ': FINAL: ' . $this->adapter->getValidationAction() );
			$is_rectified = true;
		} else {
			$status = 'UNKNOWN INCOMPLETE';
			if ( strpos( $message, 'GET_ORDERSTATUS reports that the payment is already complete.' ) === 0 ) {
				$is_rectified = true;
				$status = 'COMPLETE';
			}

			// handles the transactions we've cancelled ourselves... though if they got this far, that's a problem too.
			$errors = $results->getErrors();
			$finder = function ( $error ) {
				return $error->getErrorCode() == '1000001';
			};
			if ( !empty( $errors ) && !empty( array_filter( $errors, $finder ) ) ) {
				$is_rectified = true;
				$status = 'CANCELLED';
			}

			// apparently this is well-formed GlobalCollect for "iono". Get rid of it.
			// TODO: Verify and point to documentation.
			if ( strpos( $message, 'No processors are available.' ) === 0 ) {
				$is_rectified = true;
				$status = 'PROCESSOR NOT AVAILABLE';
			}
			$this->logger->info(
				"{$normalized['contribution_tracking_id']}: {$status}: {$message}" );
		}

		return $is_rectified;
	}

	public function getAdapter() {
		return $this->adapter;
	}

	/**
	 * Gets the global setting for the key passed in.
	 * @param string $key
	 *
	 * FIXME: Reuse GatewayAdapter::getGlobal.  Just move under a wgGC.orphan key.
	 * @return mixed Value of the variable.
	 */
	protected static function getOrphanGlobal( $key ) {
		global $wgDonationInterfaceOrphanCron;
		if ( array_key_exists( $key, $wgDonationInterfaceOrphanCron ) ) {
			return $wgDonationInterfaceOrphanCron[$key];
		} else {
			return null;
		}
	}

	/**
	 * Get the next message to process.
	 *
	 * @return array Normalized message.
	 */
	protected function getNextMessage() {
		$message = PendingDatabase::get()
			->fetchMessageByGatewayOldest( 'globalcollect' );
		if ( isset( $message['gross'] ) ) {
			$message['amount'] = $message['gross'];
			unset( $message['gross'] );
		}
		return $message;
	}

	/**
	 * Remove a message from the pending database.
	 *
	 * @param array $message
	 */
	protected function deleteMessage( $message ) {
		PendingDatabase::get()
			->deleteMessage( $message );
	}
}
