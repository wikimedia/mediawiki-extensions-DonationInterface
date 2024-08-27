<?php

class Gateway_Extras_CustomFilters_IP_Velocity extends Gateway_Extras {

	public const RAN_INITIAL = 'initial_ip_velocity_has_run';

	public const IP_VELOCITY_FILTER = 'IPVelocityFilter';

	public const IP_ALLOW_LIST = 'IPAllowList';

	public const IP_DENY_LIST = 'IPDenyList';
	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_CustomFilters_IP_Velocity
	 */
	protected static $instance;

	/**
	 * Custom filter object holder
	 * @var Gateway_Extras_CustomFilters|null
	 */
	protected $cfo;

	/**
	 * Cache instance we use to store and retrieve scores
	 * @var BagOStuff
	 */
	protected $cache_obj;

	/** @var string */
	protected $user_ip;

	protected function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object = null
	) {
		parent::__construct( $gateway_adapter );
		$this->cfo = $custom_filter_object;
		$this->cache_obj = ObjectCache::getLocalClusterInstance();
		$this->user_ip = $gateway_adapter->getData_Unstaged_Escaped( 'user_ip' );
	}

	/**
	 * Checks the global IPDenyList array for the user ip. If the user ip is listed,
	 * add the risk score for IPDenyList and return true;
	 * @return bool
	 */
	protected function isIPInDenyList(): bool {
		return DataValidator::ip_is_listed( $this->user_ip, $this->gateway_adapter->getGlobal( 'IPDenyList' ) );
	}

	protected function setIPDenyListScores(): void {
		$this->gateway_logger->info( "IP $this->user_ip present in deny list." );
		$this->cfo->addRiskScore( $this->gateway_adapter->getGlobal( 'IPDenyFailScore' ), self::IP_DENY_LIST );
	}

	/**
	 * Checks the IP attempt count in the cache. If the attempt count is greater
	 * than the global IPVelocityThreshhold return true (i.e. someone may be trying too hard).
	 * @return bool
	 */
	protected function isIPHitCountGreaterThanThreshold(): bool {
		$stored = $this->getCachedValue();

		if ( $stored ) {
			$count = count( $stored );
			$this->gateway_adapter->debugarray[] = "Found IPVelocityFilter data for $this->user_ip: " . print_r( $stored, true );
			$this->gateway_logger->info( "IPVelocityFilter: $this->user_ip has $count hits" );
			if ( $count >= $this->gateway_adapter->getGlobal( 'IPVelocityThreshhold' ) ) {
				return true;
			}
		} else {
			$this->gateway_logger->debug( "IPVelocityFilter: Found no data for $this->user_ip" );
		}

		return false;
	}

	/**
	 * Set RiskScore based on if the IP attempt surpasses the set threshold. If the Hit exceeds threshold set the
	 * IPVelocityFilter risk score to the global IPVelocityFailScore and return true.
	 * @param bool|null $ipHitExceedsThreshold
	 * @return bool
	 */
	protected function setRiskScoreBasedOnIPHitCount( ?bool $ipHitExceedsThreshold ): bool {
		if ( $ipHitExceedsThreshold ) {
			$this->cfo->addRiskScore( $this->gateway_adapter->getGlobal( 'IPVelocityFailScore' ), self::IP_VELOCITY_FILTER );
			// cool off, sucker. Muahahaha.
			return true;
		}

		$this->cfo->addRiskScore( 0, self::IP_VELOCITY_FILTER ); // want to see the explicit zero here, too.
		return false;
	}

	protected function isIPInAllowList(): bool {
		return DataValidator::ip_is_listed( $this->user_ip, $this->gateway_adapter->getGlobal( 'IPAllowList' ) );
	}

	protected function setIPAllowListScores(): void {
		$this->gateway_logger->debug( "IP present in allow list." );
		$this->cfo->addRiskScore( 0, self::IP_ALLOW_LIST );
	}

	protected function getCachedValue() {
		// return cache value for user ip
		return $this->cache_obj->get( $this->user_ip );
	}

	/**
	 * Adds the ip to the local cache, recording another attempt.
	 * If the $fail var is set and true, this denotes that the sensor has been
	 * tripped and will cause the data to live for the (potentially longer)
	 * duration defined in the IPVelocityFailDuration global
	 * @param bool $fail If this entry was added on the filter being tripped
	 * @param bool $toxic If we're adding this entry to penalize a toxic card
	 */
	protected function addIPToCache( bool $fail = false, bool $toxic = false ) {
		// need to be connected first.
		$oldvalue = $this->getCachedValue();

		$timeout = null;
		if ( $toxic ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityToxicDuration' );
		}
		if ( $timeout === null && $fail ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityFailDuration' );
		}
		if ( $timeout === null ) {
			$timeout = $this->gateway_adapter->getGlobal( 'IPVelocityTimeout' );
		}

		$result = $this->cache_obj->set( $this->user_ip, self::addHitToVelocityData( $oldvalue, $timeout ), $timeout );
		if ( !$result ) {
			$this->gateway_logger->alert( "IPVelocityFilter unable to set new cache data." );
		}
	}

	protected static function addHitToVelocityData( $stored = false, $timeout = false ): array {
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
	 * @return void
	 */
	public static function onFilter( $gateway_adapter, $custom_filter_object ) {
		if ( !$gateway_adapter->getGlobal( 'EnableIPVelocityFilter' ) ) {
			return;
		}

		$instance = self::singleton( $gateway_adapter, $custom_filter_object );
		$instance->gateway_logger->debug( 'IP Velocity onFilter!' );
		$userIPisInAllowList = $instance->isIPInAllowList();

		// Check if IP has been added to Allow list before proceeding to processor on behalf of donor
		if ( $userIPisInAllowList ) {
			$instance->setIPAllowListScores();
			return;
		}

		if ( $instance->isIPInDenyList() ) {
			$instance->setIPDenyListScores();
		}
	}

	/**
	 * Run the filter if we haven't for this session, and set a flag
	 * @param GatewayType $gateway_adapter
	 * @param Gateway_Extras_CustomFilters $custom_filter_object
	 * @return void
	 */
	public static function onInitialFilter( $gateway_adapter, $custom_filter_object ) {
		if ( !$gateway_adapter->getGlobal( 'EnableIPVelocityFilter' ) ) {
			return;
		}

		$instance = self::singleton( $gateway_adapter, $custom_filter_object );

		// Check if IP has been added to Allow list before proceeding to processor on behalf of donor
		if ( $instance->isIPInAllowList() ) {
			$instance->setIPAllowListScores();
			return;
		}

		$userIPisInDenyList = $instance->isIPInDenyList();
		if ( $userIPisInDenyList ) {
			$instance->setIPDenyListScores();
		}

		$isMultipleStageOfSameTransaction = WmfFramework::getSessionValue( self::RAN_INITIAL );

		// Do not proceed with other checks if IP is listed in DenyList or if this pass is for another stage
		// in the same transaction.
		if ( $userIPisInDenyList || $isMultipleStageOfSameTransaction ) {
			return;
		}
		// If IP exceeds set threshold, set risk scores and return.
		$ipHitCountExceedsThreshold = $instance->isIPHitCountGreaterThanThreshold();

		$instance->setRiskScoreBasedOnIPHitCount( $ipHitCountExceedsThreshold );

		$instance->addIPToCache( $ipHitCountExceedsThreshold );

		WmfFramework::setSessionValue( self::RAN_INITIAL, true );
		$instance->gateway_logger->debug( 'IP Velocity onInitialFilter!' );
	}

	protected static function singleton(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object = null
	) {
		if ( !self::$instance ) {
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
		$velocity->addIPToCache( false, true );
	}

}
