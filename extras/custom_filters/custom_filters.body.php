<?php

class Gateway_Extras_CustomFilters extends FraudFilter {

	// filter list to run on adapter construction
	const PHASE_INITIAL = 'GatewayInitialFilter';

	// filter list to run before making processor API calls
	const PHASE_VALIDATE = 'GatewayCustomFilter';

	/**
	 * A value for tracking the 'riskiness' of a transaction
	 *
	 * The action to take based on a transaction's riskScore is determined by
	 * $action_ranges.  This is built assuming a range of possible risk scores
	 * as 0-100, although you can probably bend this as needed.
	 * Due to the increased complexity introduced by custom filters, $risk_score
	 * will now be represented as an array of scores, with the name of the
	 * score's source in the keys, to promote our ability to tell what the heck
	 * is going on.
	 * @var array()
	 */
	private $risk_score;

	/**
	 * Define the action to take for a given $risk_score
	 * @var array
	 */
	protected $action_ranges;

	/**
	 * A container for an instance of self
	 */
	protected static $instance;

	protected function __construct( GatewayType $gateway_adapter ) {
		parent::__construct( $gateway_adapter ); // gateway_adapter is set in there.

		// load user action ranges and risk score
		$this->action_ranges = $this->gateway_adapter->getGlobal( 'CustomFiltersActionRanges' );
		if ( !$gateway_adapter->isBatchProcessor() ) {
			$this->risk_score = WmfFramework::getSessionValue( 'risk_scores' );
		}
		if ( !$this->risk_score ) {
			$this->risk_score = array();
		} else {
			$unnecessarily_escaped_session_contents = addslashes( json_encode( $this->risk_score ) );
			$this->fraud_logger->info( '"Loaded from session" ' . $unnecessarily_escaped_session_contents );
		}
		$this->risk_score['initial'] = $this->gateway_adapter->getGlobal( 'CustomFiltersRiskScore' );
	}

	/**
	 * Determine the action to take for a transaction based on its $risk_score
	 *
	 * @return string The action to take
	 */
	protected function determineAction() {
		$risk_score = $this->getRiskScore();
		// possible risk scores are between 0 and 100
		if ( $risk_score < 0 ) {
			$risk_score = 0;
		}
		if ( $risk_score > 100 ) {
			$risk_score = 100;
		}
		foreach ( $this->action_ranges as $action => $range ) {
			if ( $risk_score >= $range[0] && $risk_score <= $range[1] ) {
				return $action;
			}
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function addRiskScore( $score, $source ) {
		if ( !is_numeric( $score ) ) {
			throw new InvalidArgumentException( __FUNCTION__ . " Cannot add $score to risk score (not numeric). Source: $source" );
		}
		if ( !is_array( $this->risk_score ) ) {
			if ( is_numeric( $this->risk_score ) ) {
				$this->risk_score['unknown'] = (int)$this->risk_score;
			} else {
				$this->risk_score = array();
			}
		}

		$log_message = "\"$source added a score of $score\"";
		$this->fraud_logger->info( '"addRiskScore" ' . $log_message );
		$this->risk_score[$source] = $score;
	}

	/**
	 * Add up the risk scores in an array, by default $this->risk_score
	 * @param array|null $scoreArray
	 * @return float total risk score
	 */
	public function getRiskScore( $scoreArray = null ) {
		if ( is_null( $scoreArray ) ) {
			$scoreArray = $this->risk_score;
		}

		if ( is_numeric( $scoreArray ) ) {
			return $scoreArray;
		} elseif ( is_array( $scoreArray ) ) {
			$total = 0;
			foreach ( $scoreArray as $score ) {
				$total += $score;
			}
			return $total;

		} else {
			// TODO: We should catch this during setRiskScore.
			throw new InvalidArgumentException(
				__FUNCTION__ . " risk_score is neither numeric, nor an array."
				. print_r( $scoreArray, true )
			);
		}
	}

	/**
	 * Run the transaction through the custom filters
	 * @param string $phase Run custom filters attached for this phase
	 * @return bool
	 */
	protected function validate( $phase ) {
		$this->runFilters( $phase );
		$score = $this->getRiskScore();
		$this->gateway_adapter->setRiskScore( $score );
		$localAction = $this->determineAction();
		$this->gateway_adapter->setValidationAction( $localAction );

		$log_message = '"' . $localAction . "\"\t\"" . $score . "\"";

		$this->fraud_logger->info( '"Filtered" ' . $log_message );

		$log_message = '"' . addslashes( json_encode( $this->risk_score ) ) . '"';
		$this->fraud_logger->info( '"CustomFiltersScores" ' . $log_message );

		$utm = array(
			'utm_campaign' => $this->gateway_adapter->getData_Unstaged_Escaped( 'utm_campaign' ),
			'utm_medium' => $this->gateway_adapter->getData_Unstaged_Escaped( 'utm_medium' ),
			'utm_source' => $this->gateway_adapter->getData_Unstaged_Escaped( 'utm_source' ),
		);
		$log_message = '"' . addslashes( json_encode( $utm ) ) . '"';
		$this->fraud_logger->info( '"utm" ' . $log_message );

		// Always send a message if we're about to charge or redirect the donor
		// Only send a message on initial validation if things look fishy
		if ( $phase === self::PHASE_VALIDATE || $localAction !== 'process' ) {
			$this->sendAntifraudMessage( $localAction, $score, $this->risk_score );
		}

		if ( !$this->gateway_adapter->isBatchProcessor() ) {
			// Always keep the stored scores up to date
			WmfFramework::setSessionValue( 'risk_scores', $this->risk_score );
		}

		return true;
	}

	public static function onValidate( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableCustomFilters' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'custom filters onValidate!';
		return self::singleton( $gateway_adapter )->validate( self::PHASE_VALIDATE );
	}

	public static function onGatewayReady( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableCustomFilters' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'custom filters onGatewayReady!';
		return self::singleton( $gateway_adapter )->validate( self::PHASE_INITIAL );
	}

	public static function singleton( GatewayType $gateway_adapter ) {
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

	/**
	 * Gets the action calculated on the last filter run. If there are no
	 * risk scores stored in session, throws a RuntimeException. Even if
	 * all filters are disabled, we should have stored 'initial' => 0.
	 *
	 * @param GatewayType $gateway_adapter
	 * @return string
	 */
	public static function determineStoredAction( GatewayType $gateway_adapter ) {
		if (
			!WmfFramework::getSessionValue( 'risk_scores' )
		) {
			throw new RuntimeException( 'No stored risk scores' );
		}
		return self::singleton( $gateway_adapter )->determineAction();
	}

	protected function runFilters( $phase ) {
		switch ( $phase ) {
			case self::PHASE_INITIAL:
				Gateway_Extras_CustomFilters_Referrer::onInitialFilter( $this->gateway_adapter, $this );
				Gateway_Extras_CustomFilters_Source::onInitialFilter( $this->gateway_adapter, $this );
				Gateway_Extras_CustomFilters_Functions::onInitialFilter( $this->gateway_adapter, $this );
				Gateway_Extras_CustomFilters_IP_Velocity::onInitialFilter( $this->gateway_adapter, $this );
				break;
			case self::PHASE_VALIDATE:
				Gateway_Extras_CustomFilters_Functions::onFilter( $this->gateway_adapter, $this );
				Gateway_Extras_CustomFilters_MinFraud::onFilter( $this->gateway_adapter, $this );
				Gateway_Extras_CustomFilters_IP_Velocity::onFilter( $this->gateway_adapter, $this );
				break;
		}
	}
}
