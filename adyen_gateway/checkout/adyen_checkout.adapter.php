<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
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
		$transactionStatus = $authorizeResult->getStatus();
		// When authorization is successful but capture fails (or is not
		// attempted because our ValidationAction is 'review', we still
		// send the donor to the Thank You page. This is because the
		// donation can still be captured manually by Donor Relations and
		// we don't want the donor to try again.
		$paymentResult = PaymentResult::newRedirect(
			ResultPages::getThankYouPage( $this )
		);
		$gatewayTransactionId = $authorizeResult->getGatewayTxnId();
		if (
			!$authorizeResult->isSuccessful() ||
			$this->getValidationAction() === ValidationAction::REJECT
		) {
			// TODO: map any errors from $authorizationResult
			$failPage = ResultPages::getFailPage( $this );
			if ( !filter_var( $failPage, FILTER_VALIDATE_URL ) ) {
				// It's a rapidfail form, but we need an actual URL:
				$failPage = GatewayFormChooser::buildPaymentsFormURL(
					$failPage,
					[ 'gateway' => 'adyen' ]
				);
			}
			$paymentResult = PaymentResult::newFailureAndRedirect( $failPage );

			# log the error details on failure
			$errorLogMessage = "Error response from gateway: ";
			$errorLogMessage .= $authorizeResult->getStatus() . " : ";
			$errorLogMessage .= json_encode( $authorizeResult->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		} elseif (
			$authorizeResult->requiresApproval() &&
			$this->getValidationAction() === ValidationAction::PROCESS
		) {
			$captureResult = $provider->approvePayment( [
				// Note that approvePayment takes the unstaged amount
				'amount' => $this->getData_Unstaged_Escaped( 'amount' ),
				'currency' => $this->getData_Staged( 'currency' ),
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
			] );
			$transactionStatus = $captureResult->getStatus();
			if ( $captureResult->isSuccessful() ) {
				// Note: this transaction ID is different from the authorizeResult's
				// transaction ID. For credit cards, this is the one we want to store
				// in CiviCRM.
				$gatewayTransactionId = $captureResult->getGatewayTxnId();
			}
		}
		$this->addResponseData( [ 'gateway_txn_id' => $gatewayTransactionId ] );
		// Log and send the payments-init message, and clean out the session
		$this->finalizeInternalStatus( $transactionStatus );
		// Run some post-donation filters and send donation queue message
		$this->postProcessDonation();
		return $paymentResult;
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
					'return_url',
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
		// We use account_config instead
		$this->accountInfo = [];
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
