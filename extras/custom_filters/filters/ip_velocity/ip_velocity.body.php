<?php

use MediaWiki\MediaWikiServices;

class Gateway_Extras_CustomFilters_IP_Velocity extends Gateway_Extras {

	public const RAN_INITIAL = 'initial_ip_velocity_has_run';

	public const IP_VELOCITY_FILTER = 'IPVelocityFilter';

	public const IP_ALLOW_LIST = 'IPAllowList';

	public const IP_DENY_LIST = 'IPDenyList';
	public const IP_RELAY_LIST = 'IPRelayList';
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
		?Gateway_Extras_CustomFilters $custom_filter_object = null
	) {
		parent::__construct( $gateway_adapter );
		$this->cfo = $custom_filter_object;
		$this->cache_obj = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
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

			$threshold = $this->gateway_adapter->getGlobal( 'IPVelocityThreshhold' );

			// If in relay list, use a higher threshold (if configured)
			if ( $this->isIPInRelayList() ) {
				$relayThreshold = $this->gateway_adapter->getGlobal( 'IPVelocityRelayThreshold' );
				if ( $relayThreshold ) {
					$threshold = $relayThreshold;
					$this->gateway_logger->info(
						"IPVelocityFilter: Using relay threshold $threshold for $this->user_ip"
					);
				}
			}

			if ( $count >= $threshold ) {
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

	/**
	 * @return array
	 */
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

	/**
	 * @param array|false $stored
	 * @param int|false $timeout
	 */
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
		?Gateway_Extras_CustomFilters $custom_filter_object = null
	): self {
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

	protected function isIPInRelayList(): bool {
		$relay_list = $this->gateway_adapter->getGlobal( self::IP_RELAY_LIST );
		if ( !$relay_list ) {
			return false;
		}

		foreach ( $relay_list as $address ) {
			// Skip IPv6 addresses. Not supported
			if ( str_contains( $address, ':' ) ) {
				continue;
			}

			// Check for CIDR range
			if ( str_contains( $address, '/' ) ) {
				if ( $this->cidrMatch( $this->user_ip, $address ) ) {
					return true;
				}
			} elseif ( $this->user_ip === $address ) {
				// Exact IP match
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if an IP address falls within a CIDR range
	 *
	 * @see https://stackoverflow.com/questions/594112/check-whether-or-not-a-cidr-subnet-contains-an-ip-address
	 *
	 * @param string $ip IP address to check
	 * @param string $range CIDR range (e.g., '172.224.226.0/27')
	 *
	 * @return bool True if IP is within the range
	 */
	protected function cidrMatch( string $ip, string $range ): bool {
		$parts = explode( '/', $range );
		$subnet = $parts[0];
		$bits = isset( $parts[1] ) ? (int)$parts[1] : 32;

		$ipLong = ip2long( $ip );
		$subnetLong = ip2long( $subnet );

		if ( $ipLong === false || $subnetLong === false ) {
			return false;
		}

		// Create a network mask by bit-shifting. Starting with -1 (all bits set to 1),
		// we shift left by (32 - $bits) positions to create a mask with $bits number of 1s
		// followed by (32 - $bits) number of 0s.
		// Example: For a /24 network (24 bits), this creates a mask equivalent to 255.255.255.0
		$mask = -1 << ( 32 - $bits );

		// Apply the mask to the subnet address using bitwise AND to normalize it.
		// This zeros out the host portion of the subnet address, ensuring we're working
		// with the actual network address (not a host address within that network).
		// Example: If subnet is "192.168.1.50/24", this converts it to "192.168.1.0"
		// so we can properly compare network addresses.
		$subnetLong &= $mask;

		// Check if the IP address belongs to the subnet by:
		// 1. Applying the same mask to the IP address being tested (extracting its network portion)
		// 2. Comparing the masked IP with the normalized subnet address
		// 3. If they match exactly, the IP is within the subnet range
		// Example: Testing if 192.168.1.100 is in 192.168.1.0/24 would mask both to
		// 192.168.1.0 and return true since they match.
		return ( $ipLong & $mask ) === $subnetLong;
	}

}
