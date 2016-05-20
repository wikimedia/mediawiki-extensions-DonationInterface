<?php

class Gateway_Extras_CustomFilters_Functions extends Gateway_Extras {

	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_CustomFilters_Functions
	 */
	static $instance;

	/**
	 * Custom filter object holder
	 * @var Gateway_Extras_CustomFilters
	 */
	public $cfo;

	public function __construct(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {

		parent::__construct( $gateway_adapter );
		$this->cfo = $custom_filter_object;
	}

	/**
	 * @param $filterListGlobal
	 * @return bool
	 */
	public function filter( $filterListGlobal ) {

		if ( !$this->gateway_adapter->getGlobal( 'EnableFunctionsFilter' ) ||
			!count( $this->gateway_adapter->getGlobal( $filterListGlobal ) ) ){
			return true;
		}

		$functions = $this->gateway_adapter->getGlobal( $filterListGlobal );
		foreach ( $functions as $function_name => $risk_score_modifier ) {
			//run the function specified, if it exists. 
			if ( method_exists( $this->gateway_adapter, $function_name ) ) {
				$score = $this->gateway_adapter->{$function_name}();
				if ( is_null( $score ) ){
					$score = 0; //TODO: Is this the correct behavior? 
				} elseif ( is_bool( $score ) ) {
					$score = ( $score ? 0 : $risk_score_modifier );
				} elseif ( is_numeric( $score ) && $score <= 100 ) {
					$score = $score * $risk_score_modifier / 100;
				} else {
//					error_log("Function Filter: $function_name returned $score");
					throw new UnexpectedValueException( "Filter functions are returning somekinda nonsense." );
				}

				$this->cfo->addRiskScore( $score, $function_name );
			}
		}

		return TRUE;
	}

	static function onFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		$gateway_adapter->debugarray[] = 'functions onFilter hook!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter(
			'CustomFiltersFunctions'
		);
	}

	static function onInitialFilter(
		GatewayType $gateway_adapter,
		Gateway_Extras_CustomFilters $custom_filter_object
	) {
		$gateway_adapter->debugarray[] = 'functions onInitialFilter hook!';
		return self::singleton( $gateway_adapter, $custom_filter_object )->filter(
			'CustomFiltersInitialFunctions'
		);
	}

	static function singleton(
		GatewayType $gateway_adapter,
		$custom_filter_object
	) {

		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter, $custom_filter_object );
		}
		return self::$instance;
	}

}
