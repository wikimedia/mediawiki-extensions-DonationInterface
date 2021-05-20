<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class AdyenCheckoutAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'AdyenCheckout';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenCheckoutGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		$this->runSessionVelocityFilter();
		if ( $this->getValidationAction() !== ValidationAction::PROCESS ) {
			return PaymentResult::newFailure( [ new PaymentError(
				'internal-0000',
				"Failed pre-process checks for payment.",
				LogLevel::INFO
			) ] );
		}
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		// Log details of the payment in case we need to reconstruct it for
		// audit files. Note: this says 'redirecting' but we're not actually
		// sending the donor off site. Log a different prefix here and update
		// the audit grepper to find that prefix.
		$this->logPaymentDetails();
		$this->setCurrentTransaction( 'authorize' );
		$authorizeParams = $this->buildRequestArray();
		$authorizeResult = $provider->createPayment( $authorizeParams );
		$riskScores = $authorizeResult->getRiskScores();
		$this->addResponseData( [
			'avs_result' => $riskScores['avs'],
			'cvv_result' => $riskScores['cvv']
		] );
		$this->runAntifraudFilters();
		if ( !$authorizeResult->isSuccessful() ) {
			return PaymentResult::newFailure();
		}
		if (
			$authorizeResult->requiresApproval() &&
			$this->getValidationAction() === ValidationAction::PROCESS
		) {
			$captureResult = $provider->approvePayment( [
				// Note that approvePayment takes the unstaged amount
				'amount' => $this->getData_Unstaged_Escaped( 'amount' ),
				'currency' => $this->getData_Staged( 'currency' ),
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
			] );
			// FIXME: check for success
			// Note: this transaction ID is different from the authorizeResult's
			// transaction ID. For credit cards, this is the one we want to store
			// in CiviCRM.
			$this->addResponseData( [
				'gateway_txn_id' => $captureResult->getGatewayTxnId(),
			] );
		} else {
			$this->addResponseData( [
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
			] );
		}
		// So many side-effects! This call also sends opt-in on failure
		// if configured to do so, sends the payments-init message, and
		// cleans out the donor's session. FIXME: should be something
		// other than 'COMPLETE' for txns left in review due to liminal
		// fraud scores.
		$this->finalizeInternalStatus( FinalStatus::COMPLETE );
		// Runs some post-donation filters and sends donation queue message
		$this->postProcessDonation();
		return PaymentResult::newSuccess();
	}

	public function getCommunicationType() {
		return 'array';
	}

	protected function getBasedir() {
		return __DIR__;
	}

	protected function defineTransactions() {
		$this->transactions = [
			'authorize' => [
				'request' => [
					'encrypted_payment_data' => [
						'encryptedCardNumber',
						'encryptedExpiryMonth',
						'encryptedExpiryYear',
						'encryptedSecurityCode'
					],
					'amount',
					'city',
					'country',
					'currency',
					'description',
					'email',
					'first_name',
					'last_name',
					'order_id',
					'postal_code',
					'state_province',
					'street_address',
					'user_ip'
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
				]
			]
		];
	}

	protected function defineAccountInfo() {
		// TODO: Implement defineAccountInfo() method.
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'merchantReference' ],
			'ct_id' => true,
			'generate' => true,
		];
	}

	public function getCheckoutConfiguration() {
		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			// TODO: either make a getAvailablePaymentMethods request for this
			// or return a paymentMethods array customized to the donor's
			// selected paymentMethod.
			'paymentMethodsResponse' => [
				'paymentMethods' => [ [
					'brands' => [ 'visa', 'mc', 'amex', 'discover', 'cup', 'maestro', 'diners', 'jcb' ],
					'details' => [
						[ 'key' => 'encryptedCardNumber', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedSecurityCode', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryMonth', 'type' => 'cardToken' ],
						[ 'key' => 'encryptedExpiryYear', 'type' => 'cardToken' ],
						[ 'key' => 'holderName', 'optional' => true, 'type' => 'text' ]
					],
					'type' => 'scheme'
				] ]
			],
			// TODO: maybe make this dynamic based on donor location
			'environment' => $this->getAccountConfig( 'Environment' ),
		];
	}

	/**
	 * getAVSResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 * @return int
	 */
	public function getAVSResult() {
		return $this->getData_Unstaged_Escaped( 'avs_result' );
	}

	/**
	 * getCVVResult is intended to be used by the functions filter, to
	 * determine if we want to fail the transaction ourselves or not.
	 * @return int
	 */
	public function getCVVResult() {
		return $this->getData_Unstaged_Escaped( 'cvv_result' );
	}
}
