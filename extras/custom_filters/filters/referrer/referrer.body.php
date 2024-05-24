<?php

class Gateway_Extras_CustomFilters_Referrer extends Gateway_Extras {

	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_CustomFilters_Referrer
	 */
	protected static $instance;

	/**
	 * Custom filter object holder
	 * @var Gateway_Extras_CustomFilters
	 */
	protected $cfo;

	protected function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		parent::__construct( $gateway_adapter );
		$this->cfo = $custom_filter_object;
	}

	protected function filter() {
		// pull out the referrer from the gateway_adapter
		$referrer = $this->gateway_adapter->getData_Unstaged_Escaped( 'referrer' );

		// a very complex filtering algorithm for referrers
		$refRules = $this->gateway_adapter->getGlobal( 'CustomFiltersRefRules' );
		foreach ( $refRules as $regex => $risk_score_modifier ) {
			/**
			 * note that the regex pattern does NOT include delimiters.
			 * these will need to be included in your custom regex patterns.
			 */
			if ( preg_match( "$regex", $referrer ) ) {
				$this->cfo->addRiskScore( $risk_score_modifier, 'referrer' );

				// log it
				// TODO: This sucks.
				$log_msg = "\"" . addslashes( $referrer ) . "\"";
				$log_msg .= "\t\"" . addslashes( $regex ) . "\"";
				$log_msg .= "\t\"" . $this->cfo->getRiskScore() . "\"";
				$this->log(
					$this->gateway_adapter->getData_Unstaged_Escaped( 'contribution_tracking_id' ), 'Filter: Referrer', $log_msg
				);
			}
		}

		return true;
	}

	public static function onInitialFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		if ( !$gateway_adapter->getGlobal( 'EnableReferrerFilter' ) ||
			!count( $gateway_adapter->getGlobal( 'CustomFiltersRefRules' ) ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'referrer onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter();
	}

	protected static function singleton(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		if ( !self::$instance ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

}
