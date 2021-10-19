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
		$this->tuneForPaymentMethod();
		$authorizeParams = $this->buildRequestArray();
		$this->logger->info( "Calling createPayment for {$authorizeParams['email']}" );
		$authorizeResult = $provider->createPayment( $authorizeParams );
		$this->logger->info( "Returned PSP Reference {$authorizeResult->getGatewayTxnId()}" );
		$validationErrors = $authorizeResult->getValidationErrors();
		// If there are validation errors, present them for correction with a
		// 'refresh' type PaymentResult
		if ( count( $validationErrors ) > 0 ) {
			foreach ( $validationErrors as $error ) {
				// Add i18n keys to the validation errors
				$error->setMessageKey(
					'donate_interface-error-msg-' . $error->getField()
				);
				$this->logger->info(
					'createPayment call came back with validation error in ' . $error->getField()
				);
			}
			return PaymentResult::newRefresh( $validationErrors );
		}
		if ( $authorizeResult->requiresRedirect() ) {
			// Looks like we're not going to finish the payment in this
			// request - our dear donor needs to take more actions on
			// another site. Short-circuit the finalization, just stash
			// the gateway txn id and redirect them.
			$this->addResponseData( [
				'gateway_txn_id' => $authorizeResult->getGatewayTxnId()
			] );
			$redirectUrl = $authorizeResult->getRedirectUrl();
			$this->logger->info( "Redirecting to $redirectUrl" );
			return PaymentResult::newRedirect(
				$redirectUrl,
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
		$transactionStatus = $authorizeResult->getStatus();
		$this->addResponseData(
			[ 'gateway_txn_id' => $authorizeResult->getGatewayTxnId() ]
		);
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
		if ( !$authorizeResult->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			// TODO: map any errors from $authorizeResult
			// log the error details on failure
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $authorizeResult->getStatus() . " : ";
			$errorLogMessage .= json_encode( $authorizeResult->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		} elseif ( $authorizeResult->requiresApproval() ) {
			$riskScores = $authorizeResult->getRiskScores();
			$this->addResponseData( [
				'avs_result' => $riskScores['avs'] ?? 0,
				'cvv_result' => $riskScores['cvv'] ?? 0
			] );
			$this->runAntifraudFilters();
			switch ( $this->getValidationAction() ) {
				case ValidationAction::PROCESS:
					$this->logger->info( "Calling approvePayment on PSP reference {$authorizeResult->getGatewayTxnId()}" );
					$captureResult = $provider->approvePayment( [
						// Note that approvePayment takes the unstaged amount
						'amount' => $this->getData_Unstaged_Escaped( 'amount' ),
						'currency' => $this->getData_Staged( 'currency' ),
						'gateway_txn_id' => $authorizeResult->getGatewayTxnId(),
					] );
					$transactionStatus = $captureResult->getStatus();
					if ( $captureResult->isSuccessful() ) {
						// Note: this transaction ID is different from the authorizeResult's
						// transaction ID. We log this, but leave the gateway_txn_id set to
						// the ID from authorizeResult as that is what we get in the IPN.
						$this->logger->info( "Returned PSP Reference {$captureResult->getGatewayTxnId()}" );
					} else {
						$this->logger->info( 'Capture call unsuccessful' );
					}
					break;
				case ValidationAction::REJECT:
					$paymentResult = PaymentResult::newFailure();
					$this->logger->info( 'Created payment rejected by our fraud filters' );
					break;
				default:
					$this->logger->info(
						'Not capturing authorized payment - validation action is ' .
						$this->getValidationAction()
					);
			}
		}
		// recurring will return a token on the auth
		if ( $authorizeResult->getRecurringPaymentToken() ) {
			$this->addResponseData( [
				'recurring_payment_token' => $authorizeResult->getRecurringPaymentToken(),
				'processor_contact_id' => $authorizeResult->getProcessorContactID()
			] );
		}
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
					'user_ip',
					'recurring'
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
				]
			]
		];
	}

	/**
	 * Add payment-method-specific parameters to the 'authorize' transaction
	 */
	protected function tuneForPaymentMethod() {
		switch ( $this->getPaymentMethod() ) {
			case 'cc':
				$this->transactions['authorize']['request']['encrypted_payment_data'] = [
					'encryptedCardNumber',
					'encryptedExpiryMonth',
					'encryptedExpiryYear',
					'encryptedSecurityCode'
				];
				// 3D Secure is only used for cards
				$this->tuneFor3DSecure();
				break;
			case 'rtbt':
				$this->transactions['authorize']['request'][] = 'issuer_id';
				break;
			case 'apple':
				$this->transactions['authorize']['request'][] = 'payment_token';
		}
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

	public function getRequiredFields( $knownData = null ) {
		$fields = parent::getRequiredFields( $knownData );
		return array_diff( $fields, $this->getFieldsToRemove() );
	}

	public function getFormFields( $knownData = null ) {
		$fields = parent::getFormFields( $knownData );
		return array_diff_key(
			$fields,
			array_fill_keys( $this->getFieldsToRemove(), true )
		);
	}

	protected function getFieldsToRemove() {
		$method = $knownData['payment_method'] ?? $this->getData_Unstaged_Escaped( 'payment_method' );
		if ( $method === 'apple' ) {
			// For Apple Pay, do not require any of the following fields in forms,
			// regardless of what may be specified in config/country_fields.yaml
			// We can gather them instead from the Apple Pay UI.
			return [
				'first_name',
				'last_name',
				'email',
				'street_address',
				'postal_code',
				'city',
				'state_province'
			];
		}
		return [];
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
			$this->logger->info( "Handling redirectResult {$requestValues['redirectResult']}" );
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
