<?php

class Gateway_Extras_CustomFilters_Functions extends Gateway_Extras {

	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_CustomFilters_Functions
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

	/**
	 * @param string $filterListGlobal Run filters listed in a DonationInterface
	 *                                 global variable with name
	 * @return bool
	 */
	protected function filter( $filterListGlobal ) {
		$functions = $this->gateway_adapter->getGlobal( $filterListGlobal );

		if (
			!$this->gateway_adapter->getGlobal( 'EnableFunctionsFilter' ) ||
			!count( $functions )
		) {
			return true;
		}

		foreach ( $functions as $function_name => $risk_score_modifier ) {
			// run the function specified, if it exists.
			if ( method_exists( $this->gateway_adapter, $function_name ) ) {
				$score = $this->gateway_adapter->{$function_name}();
				if ( is_null( $score ) ) {
					$score = 0; // TODO: Is this the correct behavior?
				} elseif ( is_bool( $score ) ) {
					$score = ( $score ? 0 : $risk_score_modifier );
				} elseif ( is_numeric( $score ) && $score <= 100 ) {
					$score = $score * $risk_score_modifier / 100;
				} else {
// error_log("Function Filter: $function_name returned $score");
					throw new UnexpectedValueException( "Filter functions are returning somekinda nonsense." );
				}

				$this->cfo->addRiskScore( $score, $function_name );
			}
		}

		return true;
	}

	public static function onFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		$gateway_adapter->debugarray[] = 'functions onFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter(
			'CustomFiltersFunctions'
		);
	}

	public static function onInitialFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		$gateway_adapter->debugarray[] = 'functions onInitialFilter!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter(
			'CustomFiltersInitialFunctions'
		);
	}

	protected static function singleton(
		GatewayType $gateway_adapter,
		$custom_filter_object
	) {
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

}
