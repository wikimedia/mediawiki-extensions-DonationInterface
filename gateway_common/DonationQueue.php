<?php

use SmashPig\CrmLink\Messages\SourceFields;

class DonationQueue {

	protected static $instance;

	/**
	 * Singleton entrypoint
	 *
	 * @return DonationQueue
	 */
	public static function instance() {
		if ( !self::$instance ) {
			self::$instance = new DonationQueue();
		}
		return self::$instance;
	}

	public static function getVersionStamp() {
		// TODO: Core helper function.
		global $IP;
		// static to avoid duplicate fs reads
		static $sourceRevision = null;
		if ( !$sourceRevision ) {
			$versionStampPath = "$IP/.version-stamp";
			if ( file_exists( $versionStampPath ) ) {
				$sourceRevision = trim( file_get_contents( $versionStampPath ) );
			} else {
				$sourceRevision = 'unknown';
			}
		}
		return $sourceRevision;
	}

	public function push( $transaction, $queue ) {
		// TODO: This should be checked once, at a higher level.
		if ( !GatewayAdapter::getGlobal( 'EnableQueue' ) ) {
			return;
		}
		$message = $this->buildBody( $transaction );
		$this->newBackend( $queue )->push( $message );
	}

	public function pop( $queue ) {
		if ( !GatewayAdapter::getGlobal( 'EnableQueue' ) ) {
			return null;
		}
		$backend = $this->newBackend( $queue );

		return $backend->pop();
	}

	public function peek( $queue ) {
		if ( !GatewayAdapter::getGlobal( 'EnableQueue' ) ) {
			return null;
		}
		$backend = $this->newBackend( $queue );

		return $backend->peek();
	}

	/**
	 * Build a body string, given a donation data array
	 *
	 * @param array $transaction
	 *
	 * @return array Message body.  Note that we aren't json_encoding here, cos
	 * PHPQueue expects an array.
	 */
	protected function buildBody( $transaction ) {
		if ( array_key_exists( 'freeform', $transaction ) && $transaction['freeform'] ) {
			$data = $transaction;
		} else {
			// Assume anything else is a regular donation.
			$data = $this->buildTransactionMessage( $transaction );
		}
		SourceFields::addToMessage( $data );
		return $data;
	}

	/**
	 * Construct a new backend queue object
	 *
	 * Build the configuration for a named queue, and instantiate an appropriate backend object.
	 *
	 * @param string $name Queue identifier used for config lookup.
	 * @param array $options Additional values to pass to the backend's constructor.
	 * @return \PHPQueue\Interfaces\FifoQueueStore and \PHPQueue\Interfaces\KeyValueStore
	 */
	protected function newBackend( $name, $options = array() ) {
		global $wgDonationInterfaceDefaultQueueServer, $wgDonationInterfaceQueues;

		// Default to the unmapped queue name.
		$queueNameDefaults = array(
			'queue' => $name,
		);

		// Use queue-specific customizations if available, but overridden by any
		// $options passed to this function.
		if ( array_key_exists( $name, $wgDonationInterfaceQueues ) ) {
			$options = array_merge(
				$wgDonationInterfaceQueues[$name],
				$options
			);
		}

		// Merge config options, from least to greatest precedence.
		$serverConfig = array_merge(
			$queueNameDefaults,
			$wgDonationInterfaceDefaultQueueServer,
			$options
		);

		// What is this?  Make one.
		$className = $serverConfig['type'];
		if ( !class_exists( $className ) ) {
			throw new RuntimeException( "Queue backend class not found: [$className]" );
		}
		return new $className( $serverConfig );
	}

	/**
	 * Assign correct values to the array of data to be sent to the ActiveMQ server
	 * TODO: Probably something else. I don't like the way this works and neither do you.
	 *
	 * Older notes follow:
	 * Currency in receiving module has currency set to USD, should take passed variable for these
	 * PAssed both ISO and country code, no need to look up
	 * 'gateway' = globalcollect, e.g.
	 * 'date' is sent as $date("r")
	 *  so it can be translated with strtotime like Paypal transactions (correct?)
	 * Processor txn ID sent in the transaction response is assigned to 'gateway_txn_id' (PNREF)
	 * Order ID (generated with transaction) is assigned to 'contribution_tracking_id'?
	 * Response from processor is assigned to 'response'
	 *
	 * @param array $transaction values from gateway adapter
	 * @return array values normalized to wire format
	 */
	protected function buildTransactionMessage( $transaction ) {
		// specifically designed to match the CiviCRM API that will handle it
		// edit this array to include/ignore transaction data sent to the server

		$message = array(
			'contribution_tracking_id' => $transaction['contribution_tracking_id'],
			'country' => $transaction['country'],
			// the following int casting fixes an issue that is more in Drupal/CiviCRM than here.
			// The code there should also be fixed.
			'date' => (int)$transaction['date'],
			'fee' => '0',
			'gateway_account' => $transaction['gateway_account'],
			'gateway' => $transaction['gateway'],
			'gateway_txn_id' => $transaction['gateway_txn_id'],
			'language' => $transaction['language'],
			'order_id' => $transaction['order_id'],
			'payment_method' => $transaction['payment_method'],
			'payment_submethod' => $transaction['payment_submethod'],
			'response' => $transaction['response'],
			'user_ip' => $transaction['user_ip'],
			'utm_source' => $transaction['utm_source'],
		);

		// We're using this mapping for optional fields, and to cheat on not
		// transforming messages a if they are processed through this function
		// multiple times.
		$optional_keys = array(
			'anonymous' => 'anonymous',
			'city' => 'city',
			'currency' => 'currency',
			'email' => 'email',
			'first_name' => 'first_name',
			'gross' => 'amount',
			'gateway_session_id' => 'gateway_session_id',
			'last_name' => 'last_name',
			'optout' => 'optout',
			'recurring' => 'recurring',
			'risk_score' => 'risk_score',
			'state_province' => 'state_province',
			'street_address' => 'street_address',
			'supplemental_address_1' => 'supplemental_address_1',
			'subscr_id' => 'subscr_id',
			'utm_campaign' => 'utm_campaign',
			'utm_medium' => 'utm_medium',
			'postal_code' => 'postal_code',
		);
		foreach ( $optional_keys as $mkey => $tkey ) {
			if ( isset( $transaction[$tkey] ) ) {
				$message[$mkey] = $transaction[$tkey];
			} elseif ( isset( $transaction[$mkey] ) ) {
				// Just copy if it's already using the correct key.
				$message[$mkey] = $transaction[$mkey];
			}
		}

		return $message;
	}

	/**
	 * Called by the orphan rectifier to change a queue message back into a gateway
	 * transaction array, basically undoing the mappings from buildTransactionMessage.
	 *
	 * TODO: This shouldn't be necessary, see https://phabricator.wikimedia.org/T109819
	 * @deprecated by T131275
	 *
	 * @param array $transaction Queue message
	 *
	 * @return array message with queue keys remapped to gateway keys
	 */
	public static function queueMessageToNormalized( $transaction ) {
		// For now, this function assumes that we have a complete queue message.

		$rekey = array(
			'gross' => 'amount',
		);

		foreach ( $rekey as $wire => $normal ){
			if ( isset( $transaction[$wire] ) ){
				$transaction[$normal] = $transaction[$wire];
				unset( $transaction[$wire] );
			};
		}

		return $transaction;
	}
}
