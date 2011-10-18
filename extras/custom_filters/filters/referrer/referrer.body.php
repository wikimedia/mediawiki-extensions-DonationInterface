<?php

class PayflowProGateway_Extras_CustomFilters_Referrer extends PayflowProGateway_Extras {
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
		// pull out the referrer from the filter object
		$referrer = $this->cfo->gateway_data['referrer'];

		// a very complex filtering algorithm for referrers
		global $wgCustomFiltersRefRules;
		foreach ( $wgCustomFiltersRefRules as $regex => $risk_score_modifier ) {
			/**
			 * note that the regex pattern does NOT include delimiters.
			 * these will need to be included in your custom regex patterns.
			 */
			if ( preg_match( "$regex", $referrer ) ) {
				$this->cfo->risk_score += $risk_score_modifier;

				// log it
				$log_msg = "\"" . addslashes( $referrer ) . "\"";
				$log_msg .= "\t\"" . addslashes( $regex ) . "\"";
				$log_msg .= "\t\"" . $this->cfo->risk_score . "\"";
				$this->log(
					$this->cfo->gateway_data['contribution_tracking_id'],
					'Filter: Referrer',
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
