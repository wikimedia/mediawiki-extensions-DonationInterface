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

	public function push( $message, $queue ) {
		// TODO: This should be checked once, at a higher level.
		if ( !GatewayAdapter::getGlobal( 'EnableQueue' ) ) {
			return;
		}
		SourceFields::addToMessage( $message );
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
