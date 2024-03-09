<?php

use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\PaymentError;
use SmashPig\Core\UtcDate;

trait RecurringConversionTrait {
	protected $logger;

	abstract public function session_getData( $key, $subkey = null );

	abstract protected function getQueueDonationMessage(): array;

	abstract public function session_resetForNewAttempt( $force = false );

	abstract public function session_setDonorBackupData( array $donorData );

	/**
	 * If we have just made a one-time donation that is possible to convert to
	 * recurring, do the conversion. The PaymentResult will be in error if there
	 * is no eligible donation in session.
	 *
	 * @return PaymentResult
	 */
	public function doRecurringConversion(): PaymentResult {
		$sessionData = $this->session_getData( 'Donor' );
		if (
			empty( $sessionData['recurring_payment_token'] ) ||
			empty( $sessionData['gateway_txn_id'] )
		) {
			return PaymentResult::newFailure( [
				new PaymentError(
					'internal-0001',
					'No tokenized donation in session',
					LogLevel::INFO
				)
			] );
		}
		// decline post Monthly convert recurring, should start remove recurring token from gateway
		if ( isset( $_REQUEST['declineMonthlyConvert'] ) && $_REQUEST['declineMonthlyConvert'] ) {
			$message = array_merge(
				[
					'recurring' => 0,
					'monthly_convert_decline' => true,
					'order_id' => $sessionData['order_id'],
					'recurring_payment_token' => $sessionData['recurring_payment_token'],
					'processor_contact_id' => $sessionData['processor_contact_id'],
					'gateway' => $sessionData['gateway'],
					'payment_method' => $sessionData['payment_method'],
				]
			);
			QueueWrapper::push( 'donations', $message );
			$this->logger->info( "decline recurring from post monthly convert" );
		} else {
			$message = array_merge(
				$this->getQueueDonationMessage(),
				[
					'recurring' => 1,
					'txn_type' => 'subscr_signup',
					'create_date' => UtcDate::getUtcTimestamp(),
					// FIXME: Use same 'next donation date' logic as Civi extension
					'start_date' => UtcDate::getUtcTimestamp( '+1 month' ),
					'frequency_unit' => 'month',
					'frequency_interval' => 1,
					'subscr_id' => $sessionData['gateway_txn_id'],
					'recurring_payment_token' => $sessionData['recurring_payment_token'],
				]
			);
			foreach ( [ 'processor_contact_id', 'fiscal_number' ] as $optionalKey ) {
				if ( !empty( $sessionData[$optionalKey] ) ) {
					$message[$optionalKey] = $sessionData[$optionalKey];
				}
			}
			$this->logger->info(
				'Pushing transaction to queue [recurring] with amount ' .
				"{$message['currency']} {$message['gross']}"
			);

			QueueWrapper::push( 'recurring', $message );
		}

		$this->session_resetForNewAttempt( true );
		return PaymentResult::newSuccess();
	}

	/**
	 * This method is called when donor completes a payment with a gateway that supports
	 * RecurringConversion (Monthly convert) in the specified country. Doing this would foster
	 * the regeneration of the Order-ID for any additional donations. Some gateways accept the
	 * use of same Order-ID for multiple payments, some require unique Order-IDs for every donation.
	 * This would be useful for gateways that require unique Order-IDs (ex. Braintree)
	 *
	 * @param bool $force Behavior Description:
	 * $force = true: Reset for potential totally new payment, but keep
	 * numAttempt and other antifraud things (velocity data) around.
	 * $force = false: Keep all donor data around unless numAttempt has hit
	 * its max, but kill the ctid (in the likely case that it was an honest
	 * mistake)
	 */
	public function session_MoveDonorDataToBackupForRecurringConversion( $force ) {
		$donor = $this->session_getData( GatewayAdapter::DONOR );
		$this->logger->info(
			'Backing up donor session for possibility of Monthly Convert.'
		);
		$this->session_setDonorBackupData( $donor );
		$this->session_resetForNewAttempt( $force );
	}
}
