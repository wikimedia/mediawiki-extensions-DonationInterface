<?php

use Psr\Log\LoggerInterface;
use SmashPig\Core\DataStores\QueueWrapper;

abstract class FraudFilter extends Gateway_Extras {

	/**
	 * Sends messages to the blah_gateway_fraud log
	 * @var LoggerInterface
	 */
	protected $fraud_logger;

	protected function __construct( GatewayType $gateway_adapter ) {
		parent::__construct( $gateway_adapter );
		$this->fraud_logger = DonationLoggerFactory::getLogger( $this->gateway_adapter, '_fraud' );
	}

	/**
	 * Send a message to the antifraud queue
	 *
	 * @param string $validationAction
	 * @param float $totalScore
	 * @param array $scoreBreakdown
	 */
	protected function sendAntifraudMessage( $validationAction, $totalScore, $scoreBreakdown ) {
		// add a message to the fraud stats queue, so we can shovel it into the fredge.
		$stomp_msg = array(
			'validation_action' => $validationAction,
			'risk_score' => $totalScore,
			'score_breakdown' => $scoreBreakdown,
			'user_ip' => $this->gateway_adapter->getData_Unstaged_Escaped( 'user_ip' ),
		);
		// If we need much more here to help combat fraud, we could just
		// start stuffing the whole maxmind query in the fredge, too.
		// Legal said ok... but this seems a bit excessive to me at the
		// moment.

		$transaction = $this->gateway_adapter->addStandardMessageFields( $stomp_msg );

		// In the rare case that we fraud-fail before we have an order ID, use ct_id
		if ( empty( $transaction['order_id'] ) ) {
			$transaction['order_id'] = $transaction['contribution_tracking_id'];
			$this->fraud_logger->info(
				"Message had no order id, using ct_id '{$transaction['contribution_tracking_id']}'"
			);
		}
		try {
			$this->fraud_logger->info( 'Pushing transaction to payments-antifraud queue.' );
			QueueWrapper::push( 'payments-antifraud', $transaction );
		} catch ( Exception $e ) {
			$this->fraud_logger->error( 'Unable to send payments-antifraud message' );
		}
	}
}
