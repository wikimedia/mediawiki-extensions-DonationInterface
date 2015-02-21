<?php

/**
 * Configuration object for DonationLogger.  Static properties hold the most
 * current values for log identity, debug status, and message prefix.  When
 * a new instance is created, we update the public properties to reflect any
 * settings that have been overridden.  When the instance is destroyed, we
 * remove any overrides and again update the public properties.
 */
class DonationLoggerContext {

	/**
	 * Whether to log DEBUG level messages
	 * @var boolean
	 */
	static public $debug;

	/**
	 * Whether to use syslog
	 * @var boolean
	 */
	static public $syslog;

	/**
	 * Which log is this?
	 * @var string
	 */
	static public $identifier;

	/**
	 * Wrapper around callable object member
	 * @return string prefix for log messages.
	 */
	static public function getLogMessagePrefix() {
		return call_user_func( self::$getLogMessagePrefixFunction );
	}

	/**
	 * Function that will return the message prefix
	 * @var callable
	 */
	static protected $getLogMessagePrefixFunction;

	/**
	 * The shared stack of settings
	 * @var array
	 */
	static protected $settingsStack;

	/**
	 * The key of the current instance's config in $settingsStack
	 * @var int
	 */
	protected $localSettingsKey;

	/**
	 * Initializes settingsStack and public properties with default values
	 */
	static public function initialize() {
		if ( !self::$settingsStack ) {
			self::$settingsStack[] = array(
				'debug' => GatewayAdapter::getGlobal( 'LogDebug' ),
				'syslog' => GatewayAdapter::getGlobal( 'UseSyslog' ),
				'identifier' => GatewayAdapter::getIdentifier(),
				'getLogMessagePrefix' => function() { return ''; },
			);
			self::setCurrent();
		}
	}
	/**
	 * @param array $config
	 *   'debug' boolean, whether to log debug statements
	 *   'syslog' boolean, whether to log to syslog
	 *   'identifier' string, id of log we're sending to
	 *   'getLogMessagePrefix' callable
	 */
	public function __construct( $config ) {
		self::initialize();
		self::$settingsStack[] = $config;
		$this->localSettingsKey = array_search( $config, self::$settingsStack, true );
		self::setCurrent();
	}

	public function __destruct() {
		if ( isset( $this->localSettingsKey ) ) {
			unset( self::$settingsStack[$this->localSettingsKey] );
			self::setCurrent();
		}
	}

	/**
	 * Traverses the settings stack, then sets public properties to top-most
	 * corresponding values
	 */
	static protected function setCurrent() {
		$keys = array( 'debug', 'syslog', 'identifier', 'getLogMessagePrefix' );
		$updated = array();
		foreach ( self::$settingsStack as $settingsFrame  ) {
			foreach ( $keys as $key ) {
				if ( array_key_exists( $key, $settingsFrame ) ) {
					$updated[$key] = $settingsFrame[$key];
				}
			}
		}
		// We have a value for each because we initialized the stack with defaults
		self::$debug = $updated['debug'];
		self::$syslog = $updated['syslog'];
		self::$identifier = $updated['identifier'];
		self::$getLogMessagePrefixFunction = $updated['getLogMessagePrefix'];
	}
}
