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
	 * Settings added by the current instance
	 * @var array
	 */
	protected $localSettings = array();

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
		$this->pushSettings( $config );
	}

	/**
	 * Allows a class that already has a context instance to selectively
	 * override certain properties
	 * @param array $config
	 * @see __construct
	 */
	public function pushSettings( $config ) {
		self::$settingsStack[] = $config;
		array_push( $this->localSettings, $config );
		self::setCurrent();
	}

	/**
	 * Undo the last pushSettings.  If you call it too many times, it'll even
	 * undo the settings you used to construct this instance.  But that's wierd.
	 * @return array The settings you just removed
	 */
	public function popSettings() {
		if ( !$this->localSettings ) {
			throw new MWException( 'Bad programmer!  You called popSettings at least one too many times!' );
		}
		$config = $this->popInternal();
		self::setCurrent();
		return $config;
	}

	public function __destruct() {
		while ( $this->localSettings ) {
			$this->popInternal();
		}
		self::setCurrent();
	}

	protected function popInternal() {
		$config = array_pop( $this->localSettings );
		// strict search to find same array instance
		$index = array_search( $config, self::$settingsStack, true );
		unset( self::$settingsStack[$index] );
		return $config;
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
