<?php

use Psr\Log\LogLevel;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\PaymentError;
use SmashPig\Core\UtcDate;

trait RecurringConversionTrait {
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
		if ( array_key_exists( 'processor_contact_id', $sessionData ) && $sessionData['processor_contact_id'] ) {
			$message = array_merge( $message, [
				'processor_contact_id' => $sessionData['processor_contact_id']
			] );
		}
		$this->logger->info(
			'Pushing transaction to queue [recurring] with amount ' .
			"{$message['currency']} {$message['gross']}"
		);
		QueueWrapper::push( 'recurring', $message );
		$this->session_resetForNewAttempt( true );
		return PaymentResult::newSuccess();
	}

	/**
	 * This method is called when donor completes a payment with a gateway that supports
	 * RecurringConversion (Monthly convert) in the specified country. Doing this would foster
	 * the regeneration of the Order-ID for any additional donations. Some gateways accept the
	 * use of same Order-ID for multiple payments, some require unique Order-IDs for every donation.
	 * This would be useful for gateways that require unique Order-IDs (ex. Braintree)
	 */
	public function session_MoveDonorDataToBackupForRecurringConversion() {
		$donor = $this->session_getData( GatewayAdapter::DONOR );
		$this->logger->info(
		"Backing up {$donor['gateway']} Donor session for possibility of Monthly Convert."
		);
		$this->session_setDonorBackupData( $donor );
		$this->session_unsetDonorData();
	}
}
