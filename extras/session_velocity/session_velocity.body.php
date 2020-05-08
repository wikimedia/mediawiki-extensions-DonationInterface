<?php

use SmashPig\PaymentData\ValidationAction;

/**
 * Filter to control the number of times a session may hit the backend APIs in a time period.
 * Uses the standard allow / deny list objects.
 *
 * Each gateway transaction pair can have it's own decay rate and threshold. These need to be
 * in variables named *DecayRate and *Threshold. The * is there because this module has additional
 * fallback functionality on top of that provided by DonationInterface. In order of preference:
 *  * = SessionVelocity_<Transaction>_
 *  * = SessionVelocity_
 *
 * There is also a *HitScore variable which controls how many points get added to the filter for
 * each new request.
 */

class Gateway_Extras_SessionVelocityFilter extends FraudFilter {

	/**
	 * Container for an instance of self
	 * @var Gateway_Extras_SessionVelocityFilter
	 */
	private static $instance;

	// This filter stores it's information in a session array SESS_ROOT which maps like so:
	// SESS_ROOT[GatewayIdentifier][Transaction]
	// This then has the following keys:
	// SESS_SCORE  - The last known score for this gateway/transaction
	// SESS_TIME   - The last time the filter was run for this gateway/transaction
	// SESS_MULTIPLIER - The factor by which to multiply hit score for the next call
	// This allows for a logarithmically increasing penalty
	const SESS_ROOT = "DonationInterface_SessVelocity";
	const SESS_SCORE = "score";
	const SESS_TIME = "time";
	const SESS_MULTIPLIER = "multiplier";
	const SESS_MAX_SCORE = 1000;

	/**
	 * Construct the singleton instance of this class.
	 *
	 * @param GatewayType $gateway_adapter
	 *
	 * @return Gateway_Extras_SessionVelocityFilter
	 */
	private static function singleton( GatewayType $gateway_adapter ) {
		if ( !self::$instance || $gateway_adapter->isBatchProcessor() ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

	/**
	 * @param GatewayType $gateway_adapter The adapter context to log under
	 *
	 * @return bool Filter chain termination on FALSE. Also indicates that the cURL transaction
	 *  should not be performed.
	 */
	public static function onProcessorApiCall( GatewayType $gateway_adapter ) {
		if ( !$gateway_adapter->getGlobal( 'EnableSessionVelocityFilter' ) ) {
			return true;
		}
		$gateway_adapter->debugarray[] = 'Session Velocity onFilter hook!';
		return self::singleton( $gateway_adapter )->filter();
	}

	/**
	 * Although this function actually does the filtering, as this is a singleton pattern
	 * we only want one instance actually using it.
	 *
	 * @return bool false if we should stop processing
	 */
	private function filter() {
		$user_ip = $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' );

		// Determine IP status before doing anything complex
		$wl = DataValidator::ip_is_listed( $user_ip, $this->gateway_adapter->getGlobal( 'IPAllowList' ) );
		$bl = DataValidator::ip_is_listed( $user_ip, $this->gateway_adapter->getGlobal( 'IPDenyList' ) );

		if ( $wl ) {
			$this->gateway_adapter->debugarray[] = "SessionVelocity: IP present in allow list.";
			return true;
		}
		if ( $bl ) {
			$this->gateway_adapter->debugarray[] = "SessionVelocity: IP present in deny list.";
			return false;
		}

		// Open a session if it doesn't already exist
		$this->gateway_adapter->session_ensure();

		// Obtain some useful information
		$gateway = $this->gateway_adapter->getIdentifier();
		$transaction = $this->gateway_adapter->getCurrentTransaction();
		$cRequestTime = $_SERVER['REQUEST_TIME'];

		$decayRate = $this->getVar( 'DecayRate', $transaction );
		$threshold = $this->getVar( 'Threshold', $transaction );
		$multiplier = $this->getVar( 'Multiplier', $transaction );

		// Initialize the filter
		$sessionData = WmfFramework::getSessionValue( self::SESS_ROOT );
		if ( !is_array( $sessionData ) ) {
			$sessionData = [];
		}
		if ( !array_key_exists( $gateway, $sessionData ) ) {
			$sessionData[$gateway] = [];
		}
		if ( !array_key_exists( $transaction, $sessionData[$gateway] ) ) {
			$sessionData[$gateway][$transaction] = [
				$this::SESS_SCORE => 0,
				$this::SESS_TIME => $cRequestTime,
				$this::SESS_MULTIPLIER => 1,
			];
		}

		$lastTime = $sessionData[$gateway][$transaction][self::SESS_TIME];
		$score = $sessionData[$gateway][$transaction][self::SESS_SCORE];
		$lastMultiplier = $sessionData[$gateway][$transaction][self::SESS_MULTIPLIER];

		// Update the filter if it's stale
		if ( $cRequestTime != $lastTime ) {
			$score = max( 0, $score - ( ( $cRequestTime - $lastTime ) * $decayRate ) );
			$score += $this->getVar( 'HitScore', $transaction ) * $lastMultiplier;
			$score = min( $score, self::SESS_MAX_SCORE );

			$sessionData[$gateway][$transaction][$this::SESS_SCORE] = $score;
			$sessionData[$gateway][$transaction][$this::SESS_TIME] = $cRequestTime;
			$sessionData[$gateway][$transaction][$this::SESS_MULTIPLIER] = $lastMultiplier * $multiplier;
		}

		// Store the results
		WmfFramework::setSessionValue( self::SESS_ROOT, $sessionData );

		// Analyze the filter results
		if ( $score >= $threshold ) {
			// Ahh!!! Failure!!! Sloooooooow doooowwwwnnnn
			$this->fraud_logger->alert( "SessionVelocity: Rejecting request due to score of $score" );
			$this->sendAntifraudMessage( ValidationAction::REJECT, $score, [ 'SessionVelocity' => $score ] );
			$retval = false;
		} else {
			$retval = true;
		}

		$this->fraud_logger->debug(
			"SessionVelocity: ($gateway, $transaction) Score: $score, " .
				"AllowAction: $retval, DecayRate: $decayRate, " .
				"Threshold: $threshold, Multiplier: $lastMultiplier"
		);

		return $retval;
	}

	/**
	 * Providing that additional layer of indirection and confusion.
	 *
	 * @param string $baseVar The root name of the variable
	 * @param string $txn The name of the transaction
	 *
	 * @return mixed The contents of the configuration variable
	 */
	private function getVar( $baseVar, $txn ) {
		$var = $this->gateway_adapter->getGlobal( 'SessionVelocity_' . $txn . '_' . $baseVar );
		if ( !isset( $var ) ) {
			$var = $this->gateway_adapter->getGlobal( 'SessionVelocity_' . $baseVar );
		}

		return $var;
	}
}
