<?php

class PayflowProGateway_Extras_CustomFilters_Source extends PayflowProGateway_Extras {
	/**
	 * Container for an instance of self
	 * @var object
	 */
	static $instance;

	/**
	 * Custom filter object holder
	 * @var object
	 */
	public $cfo;

	public function __construct( &$custom_filter_object ) {
		parent::__construct();
		$this->cfo =& $custom_filter_object;
	}

	public function filter() {
		// pull out the source from the filter object
		$source = $this->cfo->gateway_data['utm_source'];
	
		// a very complex filtering algorithm for sources
		global $wgCustomFiltersSrcRules;
		foreach ( $wgCustomFiltersSrcRules as $regex => $risk_score_modifier ) {
			if( preg_match( "/$regex/", $source )) {
				$this->cfo->risk_score += $risk_score_modifier;
		
				// log it
				$log_msg = "\"" . addslashes($source) . "\"";
				$log_msg .= "\t\"" . addslashes($regex) . "\"";
				$log_msg .= "\t\"" . $this->cfo->risk_score . "\"";
				$this->log( 
					$this->cfo->gateway_data['contribution_tracking_id'],
					'Filter: Source',
					$log_msg
				);
			}
		}

		return TRUE;
	}

	static function onFilter( &$custom_filter_object ) {
		return self::singleton( $custom_filter_object )->filter();
	}

	static function singleton( &$custom_filter_object ) {
		if ( !self::$instance ) {
			self::$instance = new self( $custom_filter_object );
		}
		return self::$instance;
	}
}
