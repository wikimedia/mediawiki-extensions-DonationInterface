<?php

class Gateway_Extras_CustomFilters extends FraudFilter {

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
	public $action_ranges;

	/**
	 * A container for an instance of self
	 */
	static $instance;

	public function __construct( GatewayType $gateway_adapter ) {
		parent::__construct( $gateway_adapter ); //gateway_adapter is set in there. 
		// load user action ranges and risk score		
		$this->action_ranges = $this->gateway_adapter->getGlobal( 'CustomFiltersActionRanges' );
		$this->risk_score = $this->gateway_adapter->getRequest()->getSessionData( 'risk_scores' );
		if ( !$this->risk_score ) {
			$this->risk_score = array();
		}
		$this->risk_score['initial'] = $this->gateway_adapter->getGlobal( 'CustomFiltersRiskScore' );
	}

	/**
	 * Determine the action to take for a transaction based on its $risk_score
	 *
	 * @return string The action to take
	 */
	public function determineAction() {
		$risk_score = $this->getRiskScore();
		// possible risk scores are between 0 and 100
		if ( $risk_score < 0 )
			$risk_score = 0;
		if ( $risk_score > 100 )
			$risk_score = 100;
		foreach ( $this->action_ranges as $action => $range ) {
			if ( $risk_score >= $range[0] && $risk_score <= $range[1] ) {
				return $action;
			}
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function addRiskScore( $score, $source ){
		if ( !is_numeric( $score ) ){
			throw new InvalidArgumentException(__FUNCTION__ . " Cannot add $score to risk score (not numeric). Source: $source" );
		}
		if ( !is_array( $this->risk_score ) ){
			if ( is_numeric( $this->risk_score ) ){
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
			foreach ( $scoreArray as $score ){
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
	 */
	public function validate( $hook ) {
		// expose a hook for custom filters
		WmfFramework::runHooks( $hook, array( $this->gateway_adapter, $this ) );
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

		if ( $this->shouldSendMessage( $score ) ) {
			$this->sendAntifraudMessage( $localAction, $this->getRiskScore(), $this->risk_score );
		}

		// Always keep the stored scores up to date
		$this->gateway_adapter->getRequest()->setSessionData( 'risk_scores', $this->risk_score );

		return TRUE;
	}

	/**
	 * Determine if we should send an antifraud message
	 * @param float $score total risk score for this run
	 * @return bool true if a queue message is warranted
	 */
	protected function shouldSendMessage( $score ) {
		// We only send a message when the total changes
		$storedScores = $this->gateway_adapter->getRequest()->getSessionData( 'risk_scores' );
		if ( is_array( $storedScores ) ) {
			$storedTotal = $this->getRiskScore( $storedScores );
		} else {
			// Nothing stored? Set this to an impossible value so we
			// send a message even if our new total is zero.
			$storedTotal = -1;
		}

		// We don't want to send a message if we didn't run anything,
		// i.e., if $this->risk_score looks like this:
		$shirked = array(
			'initial' => $this->gateway_adapter->getGlobal( 'CustomFiltersRiskScore' )
		);

		if ( $this->risk_score != $shirked && $storedTotal !== $score ) {
			// We ran something, and the total changed
			return true;
		}

		return false;
	}

	static function onValidate( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableCustomFilters' ) ){
			return true;
		}
		$gateway_adapter->debugarray[] = 'custom filters onValidate hook!';
		return self::singleton( $gateway_adapter )->validate( 'GatewayCustomFilter' );
	}

	static function onGatewayReady( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableCustomFilters' ) ){
			return true;
		}
		$gateway_adapter->debugarray[] = 'custom filters onGatewayReady hook!';
		return self::singleton( $gateway_adapter )->validate( 'GatewayInitialFilter' );
	}

	static function singleton( GatewayType $gateway_adapter ) {
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

}
