<?php

class Gateway_Extras_CustomFilters extends Gateway_Extras {

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

	/**
	 * Sends messages to the blah_gateway_fraud log
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $fraud_logger;

	public function __construct( GatewayType &$gateway_adapter ) {
		parent::__construct( $gateway_adapter ); //gateway_adapter is set in there. 
		// load user action ranges and risk score		
		$this->action_ranges = $this->gateway_adapter->getGlobal( 'CustomFiltersActionRanges' );
		$this->risk_score['initial'] = $this->gateway_adapter->getGlobal( 'CustomFiltersRiskScore' );
		$this->fraud_logger = DonationLoggerFactory::getLogger( $this->gateway_adapter, '_fraud' );
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

		$this->gateway_adapter->addRiskScore( $score );
	}
	

	/**
	 * @throws InvalidArgumentException
	 */
	public function getRiskScore() {

		if ( is_numeric( $this->risk_score ) ) {
			return $this->risk_score;

		} elseif ( is_array( $this->risk_score) ) {
			$total = 0;
			foreach ( $this->risk_score as $score ){
				$total += $score;
			}
			return $total;

		} else {
			// TODO: We should catch this during setRiskScore.
			throw new InvalidArgumentException( __FUNCTION__ . " risk_score is neither numeric, nor an array." . print_r( $this->risk_score, true ) );
		}
	}
	

	/**
	 * Run the transaction through the custom filters
	 */
	public function validate() {
		// expose a hook for custom filters
		WmfFramework::runHooks( 'GatewayCustomFilter', array( &$this->gateway_adapter, &$this ) );
		$localAction = $this->determineAction();
		$this->gateway_adapter->setValidationAction( $localAction );

		$log_message = '"' . $localAction . "\"\t\"" . $this->getRiskScore() . "\"";
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

		//add a message to the fraud stats queue, so we can shovel it into the fredge.
		$stomp_msg = array (
			'validation_action' => $localAction,
			'risk_score' => $this->getRiskScore(),
			'score_breakdown' => $this->risk_score,
			'php-message-class' => 'SmashPig\CrmLink\Messages\DonationInterfaceAntifraud',
			'user_ip' => $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' ),
		);
		//If we need much more here to help combat fraud, we could just
		//start stuffing the whole maxmind query in the fredge, too.
		//Legal said ok... but this seems a bit excessive to me at the
		//moment. 

		$transaction = $this->gateway_adapter->makeFreeformStompTransaction( $stomp_msg );

		try {
			$this->fraud_logger->info( 'Pushing transaction to payments-antifraud queue.' );
			DonationQueue::instance()->push( $transaction, 'payments-antifraud' );
		} catch ( Exception $e ) {
			$this->fraud_logger->error( 'Unable to send payments-antifraud message' );
		}

		return TRUE;
	}

	static function onValidate( &$gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableCustomFilters' ) ){
			return true;
		}
		$gateway_adapter->debugarray[] = 'custom filters onValidate hook!';
		return self::singleton( $gateway_adapter )->validate();
	}

	static function singleton( &$gateway_adapter ) {
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

}
