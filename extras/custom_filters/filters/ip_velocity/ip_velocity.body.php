<?php

class Gateway_Extras_CustomFilters_IP_Velocity extends Gateway_Extras {

	const RAN_INITIAL = 'initial_ip_velocity_has_run';

	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_CustomFilters_IP_Velocity
	 */
	protected static $instance;

	/**
	 * Custom filter object holder
	 * @var Gateway_Extras_CustomFilters
	 */
	protected $cfo;

	/**
	 * Cache instance we use to store and retrieve scores
	 * @var BagOStuff
	 */
	protected $cache_obj;

	protected function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object = null
	) {
		parent::__construct( $gateway_adapter );
		$this->cfo = $custom_filter_object;
		$this->cache_obj = ObjectCache::getLocalClusterInstance();
	}

	protected function filter() {
		$user_ip = $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' );

		// first, handle the whitelist / blacklist before you do anything else.
		if ( DataValidator::ip_is_listed( $user_ip, $this->gateway_adapter->getGlobal( 'IPWhitelist' ) ) ) {
			$this->gateway_adapter->debugarray[] = "IP present in whitelist.";
			$this->cfo->addRiskScore( 0, 'IPWhitelist' );
			return true;
		}
		// TODO: this blacklist business should happen elsewhere, and on every hit.
		if ( DataValidator::ip_is_listed( $user_ip, $this->gateway_adapter->getGlobal( 'IPBlacklist' ) ) ) {
			$this->gateway_adapter->debugarray[] = "IP present in blacklist.";
			$this->cfo->addRiskScore( $this->gateway_adapter->getGlobal( 'IPVelocityFailScore' ), 'IPBlacklist' );
			return true;
		}

		$stored = $this->getCachedValue();

		if ( !$stored ) { // we don't have anything in memcache for this dude yet.
			$this->gateway_adapter->debugarray[] = "Found no memcached data for $user_ip";
			$this->cfo->addRiskScore( 0, 'IPVelocityFilter' ); // want to see the explicit zero
			return true;
		} else {
			$count = count( $stored );
			$this->gateway_adapter->debugarray[] = "Found a memcached bit of data for $user_ip: " . print_r( $stored, true );
			$this->gateway_logger->info( "IPVelocityFilter: $user_ip has $count hits" );
			if ( $count >= $this->gateway_adapter->getGlobal( 'IPVelocityThreshhold' ) ) {
				$this->cfo->addRiskScore( $this->gateway_adapter->getGlobal( 'IPVelocityFailScore' ), 'IPVelocityFilter' );
				// cool off, sucker. Muahahaha.
				$this->addNowToCachedValue( $stored, true );
			} else {
				$this->cfo->addRiskScore( 0, 'IPVelocityFilter' ); // want to see the explicit zero here, too.
			}
		}

		// fail open, in case memcached doesn't work.
		return true;
	}

	protected function postProcess() {
		$this->addNowToCachedValue();
		return true;
	}

	protected function getCachedValue() {
		// check to see if the user ip is in memcache
		$user_ip = $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' );
		$stored = $this->cache_obj->get( $user_ip );
		return $stored;
	}

	/**
	 * Adds the ip to the local memcache, recording another attempt.
	 * If the $fail var is set and true, this denotes that the sensor has been
	 * tripped and will cause the data to live for the (potentially longer)
	 * duration defined in the IPVelocityFailDuration global
	 * @param array|null $oldvalue The value we've just pulled from memcache for this
	 * ip address
	 * @param bool $fail If this entry was added on the filter being tripped
	 * @param bool $toxic If we're adding this entry to penalize a toxic card
	 */
	protected function addNowToCachedValue( $oldvalue = null, $fail = false, $toxic = false ) {
		// need to be connected first.
		if ( is_null( $oldvalue ) ) {
			$oldvalue = $this->getCachedValue();
		}

		$timeout = null;
		if ( $toxic ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityToxicDuration' );
		}
		if ( is_null( $timeout ) && $fail ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityFailDuration' );
		}
		if ( is_null( $timeout ) ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityTimeout' );
		}

		$user_ip = $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' );
		$ret = $this->cache_obj->set( $user_ip, self::addNowToVelocityData( $oldvalue, $timeout ), $timeout );
		if ( !$ret ) {
			$this->gateway_logger->alert( "IPVelocityFilter unable to set new memcache data." );
		}
	}

	protected static function addNowToVelocityData( $stored = false, $timeout = false ) {
		$new_velocity_records = [];
		$nowstamp = time();
		if ( is_array( $stored ) ) {
			foreach ( $stored as $timestamp ) {
				if ( !$timeout || $timestamp > ( $nowstamp - $timeout ) ) {
					$new_velocity_records[] = $timestamp;
				}
			}
		}
		$new_velocity_records[] = $nowstamp;
		return $new_velocity_records;
	}

	/**
	 * This is called when we're actually talking to the processor.
	 * We don't call on the first attempt in this session, since
	 * onInitialFilter already struck once.
	 * @param GatewayType $gateway_adapter
	 * @param Gateway_Extras_CustomFilters $custom_filter_object
	 * @return bool
	 */
	public static function onFilter( $gateway_adapter, $custom_filter_object ) {
		if ( !$gateway_adapter->getGlobal( 'EnableIPVelocityFilter' ) ) {
			return true;
		}
		if ( WmfFramework::getSessionValue( self::RAN_INITIAL )
			&& !WmfFramework::getSessionValue( 'numAttempt' )
		) {
			// We're on the first attempt, already counted in onInitialFilter
			return true;
		}
		$gateway_adapter->debugarray[] = 'IP Velocity onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter();
	}

	/**
	 * Run the filter if we haven't for this session, and set a flag
	 * @param GatewayType $gateway_adapter
	 * @param Gateway_Extras_CustomFilters $custom_filter_object
	 * @return bool
	 */
	public static function onInitialFilter( $gateway_adapter, $custom_filter_object ) {
		if ( !$gateway_adapter->getGlobal( 'EnableIPVelocityFilter' ) ) {
			return true;
		}
		if ( WmfFramework::getSessionValue( self::RAN_INITIAL ) ) {
			return true;
		}

		WmfFramework::setSessionValue( self::RAN_INITIAL, true );
		$gateway_adapter->debugarray[] = 'IP Velocity onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter();
	}

	public static function onPostProcess( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableIPVelocityFilter' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'IP Velocity onPostProcess!';
		return self::singleton( $gateway_adapter )->postProcess();
	}

	protected static function singleton(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object = null
	) {
		# FIXME: Why always construct a new object if we're batch processing?
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

	/**
	 * Add a hit to this IP's history for a toxic card.  This is designed to be
	 * called outside of the usual filter callbacks so we record nasty attempts
	 * even when the filters aren't called.
	 * @param GatewayType $gateway adapter instance with user_ip set
	 */
	public static function penalize( GatewayType $gateway ) {
		$logger = DonationLoggerFactory::getLogger( $gateway );
		$logger->info( 'IPVelocityFilter penalizing IP address '
			. $gateway->getData_Unstaged_Escaped( 'user_ip' )
			. ' for toxic card attempt.' );

		$velocity = self::singleton(
			$gateway,
			Gateway_Extras_CustomFilters::singleton( $gateway )
		);
		$velocity->addNowToCachedValue( null, false, true );
	}

}
