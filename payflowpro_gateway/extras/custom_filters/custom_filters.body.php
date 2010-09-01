<?php

class PayflowProGateway_Extras_CustomFilters extends PayflowProGateway_Extras {
	/**
	 * A value for tracking the 'riskiness' of a transaction
	 *
	 * The action to take based on a transaction's riskScore is determined by 
	 * $action_ranges.  This is built assuming a range of possible risk scores
	 * as 0-100, although you can probably bend this as needed.
	 * @var public int
	 */
	public $risk_score;

	/** 
	 * Define the action to take for a given $risk_score
	 * @var public array
	 */
	public $action_ranges;

	/**
	 * A container for the gateway object
	 *
	 * This gets populated on construction.
	 * @var object
	 */
	public $gateway_object;

	/**
	 * A container for data from the gateway
	 *
	 * This gets populated on construction.
	 */
	public $gateway_data;

	/**
	 * A container for an instance of self
	 */
	static $instance;

	public function __construct( &$pfp_gateway_object, &$data ) {
		parent::__construct();

		$this->gateway_object =& $pfp_gateway_object;
		$this->gateway_data =& $data;

		// load user action ranges and risk score
		global $wgPayflowGatewayCustomFiltersActionRanges, $wgPayflowGatewayCustomFiltersRiskScore;
		if ( isset( $wgPayflowGatewayCustomFiltersActionRanges )) $this->action_ranges = $wgPayflowGatewayCustomFiltersActionRanges;
		if ( isset( $wgPayflowGatewayCustomFiltersRiskScore )) $this->risk_score = $wgPayflowGatewayCustomFiltersRiskScore;
	}

	/**
	 * Determine the action to take for a transaction based on its $risk_score
	 *
	 * @return string The action to take
	 */
	public function determineAction() {
		// possible risk scores are between 0 and 100
		if ( $this->risk_score < 0 ) $this->risk_score = 0;
		if ( $this->risk_score > 100 ) $this->risk_score = 100;

		foreach ( $this->action_ranges as $action => $range ) { 
		    if ( $this->risk_score >= $range[0] && $this->risk_score <= $range[1] ) { 
				return $action;
			}
		}
	}

	/**
	 * Run the transaction through the custom filters
	 */
	public function validate() {
		// expose a hook for custom filters
		wfRunHooks( 'PayflowGatewayCustomFilter', array( $this ));
		$this->gateway_object->action = $this->determineAction();

		$log_msg = '"' . $this->gateway_object->action . "\"\t\"" . $this->risk_score . "\""; 
		$this->log( $this->gateway_data['contribution_tracking_id'], 'Filtered', $log_msg );
		return TRUE;
	}

	static function onValidate( &$pfp_gateway_object, &$data ) {
		return self::singleton( $pfp_gateway_object, $data )->validate();
	}

	static function singleton( &$pfp_gateway_object, &$data ) {
		if ( !self::$instance ) {
			self::$instance = new self( $pfp_gateway_object, $data );
		}
		return self::$instance;
	}
}
