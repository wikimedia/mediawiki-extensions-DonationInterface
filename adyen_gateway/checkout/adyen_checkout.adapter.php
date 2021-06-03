<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentDetailResponse;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class AdyenCheckoutAdapter extends GatewayAdapter {
	const GATEWAY_NAME = 'AdyenCheckout';
	const IDENTIFIER = 'adyen';
	const GLOBAL_PREFIX = 'wgAdyenCheckoutGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->setCurrentTransaction( 'authorize' );
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
		$this->tuneFor3DSecure();
		$authorizeParams = $this->buildRequestArray();
		$authorizeResult = $provider->createPayment( $authorizeParams );
		if ( $authorizeResult->requiresRedirect() ) {
			// Looks like we're not going to finish the payment in this
			// request - our dear donor needs to take more actions on
			// another site. Short-circuit the finalization, just stash
			// the gateway txn id and redirect them.
			$this->addResponseData( [
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId()
			] );
			return PaymentResult::newRedirect(
				$authorizeResult->getRedirectUrl(),
				$authorizeResult->getRedirectData()
			);
		}
		// If we DON'T need to redirect, handle the fraud checks and any
		// necessary payment capture step here and now.
		return $this->handleCreatedPayment( $provider, $authorizeResult );
	}

	/**
	 * After a payment has been created and we have the processor-side fraud results
	 * (AVS & CVV checks), run our fraud filters and capture the payment if needed.
	 *
	 * @param IPaymentProvider $provider
	 * @param PaymentDetailResponse $authorizeResult
	 * @return PaymentResult
	 * @throws MWException
	 */
	protected function handleCreatedPayment(
		IPaymentProvider $provider, PaymentDetailResponse $authorizeResult
	): PaymentResult {
		$riskScores = $authorizeResult->getRiskScores();
		$this->addResponseData( [
			'avs_result' => $riskScores['avs'] ?? 0,
			'cvv_result' => $riskScores['cvv'] ?? 0
		] );
		$this->runAntifraudFilters();
		$transactionStatus = $authorizeResult->getStatus();
		// When authorization is successful but capture fails (or is not
		// attempted because our ValidationAction is 'review', we still
		// send the donor to the Thank You page. This is because the
		// donation can still be captured manually by Donor Relations and
		// we don't want the donor to try again.
		// FIXME: this should be a newSuccess so we don't trigger extra
		// pending logging.
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
			if ( !$authorizeResult->isSuccessful() ) {
				$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			} else {
				$errorLogMessage = 'Created payment rejected by our fraud filters: ';
			}
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
					'issuer_id',
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

	/**
	 * If the device fingerprinting data needed for 3D Secure is staged up,
	 * add it to the transaction structure. Has to be called after staging
	 * but before getting the transaction structure (gross).
	 */
	protected function tuneFor3DSecure() {
		if ( $this->getData_Staged( 'user_agent' ) !== null ) {
			$this->transactions['authorize']['request']['browser_info'] = [
				'userAgent',
				'acceptHeader',
				'language',
				'colorDepth',
				'screenHeight',
				'screenWidth',
				'timeZoneOffset',
				'javaEnabled'
			];
		}
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
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		$methodparams['country'] = $this->staged_data['country'];
		$methodparams['currency'] = $this->staged_data['currency'];
		$methodparams['amount'] = $this->staged_data['amount'];
		$methodparams['language'] = $this->staged_data['language'];
		// this has all the payment methods available
		// Todo: what happens if it doesnt return anything
		$paymentMethodResult = $provider->getPaymentMethods( $methodparams )->getRawResponse();

		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			'paymentMethodsResponse' => $paymentMethodResult,
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

	public function processDonorReturn( $requestValues ) {
		if (
			isset( $requestValues['redirectResult'] )
		) {
			// We're dealing with a donor coming back from a page they have
			// been redirected to in order to make a payment, e.g. the 'Redirect'
			// 3D Secure flow after verifying the transaction with their bank.
			// https://docs.adyen.com/online-payments/3d-secure/redirect-3ds2-3ds1/web-drop-in#handle-the-redirect-result
			$redirectResult = urldecode( $requestValues['redirectResult'] );
			$provider = PaymentProviderFactory::getProviderForMethod(
				$this->getPaymentMethod()
			);
			$detailsResult = $provider->getHostedPaymentDetails( $redirectResult );
			return $this->handleCreatedPayment( $provider, $detailsResult );
		}
		// Default behavior is to finalize and return success
		return parent::processDonorReturn( $requestValues );
	}
}
