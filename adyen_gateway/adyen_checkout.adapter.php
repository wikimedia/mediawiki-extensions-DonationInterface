<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

class AdyenCheckoutAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

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

		// If we are going to ask for a monthly donation after a one-time donation completes, set the
		// recurring param to 1 to tokenize the payment.
		if ( $this->showMonthlyConvert() ) {
			$authorizeParams['recurring'] = 1;
			// Since we're not sure if we're going to ever use the token, flag the transaction as
			// 'card on file' rather than 'subscription' (the default for recurring). This may avoid
			// donor complaints of one-time donations appearing as recurring on their card statement.
			$authorizeParams['recurring_model'] = RecurringModel::CARD_ON_FILE;
		}
		$this->logger->info( "Calling createPayment for {$authorizeParams['email']}" );
		$authorizeResult = $provider->createPayment( $authorizeParams );
		$this->logger->info( "Returned PSP Reference {$authorizeResult->getGatewayTxnId()}" );
		$validationErrors = $authorizeResult->getValidationErrors();
		// If there are validation errors, present them for correction with a
		// 'refresh' type PaymentResult
		if ( count( $validationErrors ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $validationErrors );
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
		$paymentResult = PaymentResult::newSuccess();
		if ( !$authorizeResult->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			// TODO: map any errors from $authorizeResult
			// log the error details on failure
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $authorizeResult->getStatus() . " : ";
			$errorLogMessage .= json_encode( $authorizeResult->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		} elseif ( $authorizeResult->requiresApproval() ) {
			$this->runFraudFiltersIfNeeded( $authorizeResult );
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
						if ( $this->showMonthlyConvert() ) {
							$this->logger->info( "Displaying monthly convert modal" );
							$paymentResult = PaymentResult::newSuccess();
						}
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
			if ( $this->showMonthlyConvert() ) {
				$this->session_addDonorData();
			}
		}
		// Log and send the payments-init message, and clean out the session
		$this->finalizeInternalStatus( $transactionStatus );

		// Run some post-donation filters and send donation queue message
		// NOTE: recurring iDEAL will not be added to the donations queue
		// as the recurring_token is still needed from the ipn message.
		// We are still sending it through the postProcessDonation flow
		// to get the additional filters and logging
		$this->postProcessDonation();
		return $paymentResult;
	}

	public function getCommunicationType() {
		return 'array';
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
			case 'google':
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
		// The Adyen3DSecure staging helper will set this user_agent key
		// in the staged data when the country and currency are configured
		// to need 3DSecure. If that key is set, we want to send the whole
		// browser_info blob as a part of the authorize API call.
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

	public function getGoogleAllowedNetworks() {
		$general = [ 'AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA' ];
		if ( isset( $this->config[ 'payment_submethods' ] ) ) {
			if ( isset( $this->config[ 'payment_submethods' ][ 'mir' ] ) ) {
				$general[] = 'MIR';
			}
			if ( isset( $this->config[ 'payment_submethods' ][ 'interac' ] ) ) {
				$general[] = 'INTERAC';
			}
		}
		return $general;
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
		} elseif ( $method === 'google' ) {
			return [
				'street_address',
				'postal_code',
				'city'
			];
		}
		return [];
	}

	public function getCheckoutConfiguration() {
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		$methodParams = [
			'country' => $this->staged_data['country'],
			'currency' => $this->staged_data['currency'],
			'amount' => $this->staged_data['amount'],
			'language' => $this->staged_data['language']
		];
		// This should have all the payment methods available
		$paymentMethodResult = $provider->getPaymentMethods( $methodParams );
		if ( $paymentMethodResult->hasErrors() ) {
			foreach ( $paymentMethodResult->getErrors() as $error ) {
				$this->logger->warning( "paymentMethod lookup error: {$error->getMessage()}" );
			}
			foreach ( $paymentMethodResult->getValidationErrors() as $validationError ) {
				$this->logger->warning( "paymentMethod lookup validation error with parameter: {$validationError->getField()}" );
			}
			throw new RuntimeException( "Errors returned from getPaymentMethods" );
		}
		$paymentMethodString = $paymentMethodResult->getRawResponse();

		return [
			'clientKey' => $this->getAccountConfig( 'ClientKey' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			'paymentMethodsResponse' => $paymentMethodString,
			// TODO: maybe make this dynamic based on donor location
			'environment' => $this->getAccountConfig( 'Environment' ),
			'merchantAccountName' => $this->account_name,
			'googleMerchantId' => $this->getAccountConfig( 'GoogleMerchantId' ),
			'googleAllowedNetworks' => $this->getGoogleAllowedNetworks(),
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
			$this->logger->debug(
				'Hosted payment detail response: ' . json_encode( $detailsResult->getRawResponse() )
			);
			return $this->handleCreatedPayment( $provider, $detailsResult );
		}
		// Default behavior is to finalize and return success
		return parent::processDonorReturn( $requestValues );
	}

	/**
	 * Adds a catch to stop recurring iDEALs from being sent to the donations queue
	 * but otherwise sends to the queues as normal
	 *
	 * @param string $queue What queue to send the message to
	 * @param bool $contactOnly If we only have the donor's contact information
	 *
	 */
	protected function pushMessage( $queue, $contactOnly = false ) {
		// Don't send recurring iDEALs to the donations queue
		// recurring iDEAL's recurring_payment_token comes in on a RECURRING_CONTRACT
		// ipn message, SmashPig's ipn listener is what will push them to the donations queue
		if (
			$this->getPaymentSubmethod() == 'rtbt_ideal' &&
			$this->getData_Unstaged_Escaped( 'recurring' ) &&
			$queue == 'donations'
		) {
			return;
		}
		// Send all other adyen messages to the queues as normal
		parent::pushMessage( $queue, $contactOnly );
	}

	/**
	 * Runs antifraud filters if the appropriate for the current payment method.
	 * Sets $this->action to one of the ValidationAction constants.
	 *
	 * @param PaymentDetailResponse $authorizeResult
	 */
	protected function runFraudFiltersIfNeeded( PaymentDetailResponse $authorizeResult ): void {
		if ( in_array( $this->getPaymentMethod(), [ 'apple', 'google' ], true ) ) {
			// Adyen guidance is that Apple Pay fraud rates are minuscule enough
			// to skip fraud filters. Google Pay seems to get a lot of spurious
			// AVS failures.
			$this->setValidationAction( ValidationAction::PROCESS );
		} else {
			$riskScores = $authorizeResult->getRiskScores();
			$this->addResponseData( [
				'avs_result' => $riskScores['avs'] ?? 0,
				'cvv_result' => $riskScores['cvv'] ?? 0
			] );
			$this->runAntifraudFilters();
		}
	}

	/**
	 * @param array $validationErrors
	 * @return PaymentResult
	 */
	protected function getLocalizedValidationErrorResult( array $validationErrors ): PaymentResult {
		// Errors from SmashPig don't have message* parameters set,
		// so we create a new array of localized errors
		// FIXME: those should probably be different classes
		// see https://phabricator.wikimedia.org/T294957
		$localizedErrors = [];
		foreach ( $validationErrors as $error ) {
			$field = $error->getField();
			if ( $field === 'payment_submethod' ) {
				// This means the donor tried an unsupported card type.
				$messageKey = 'donate_interface-donate-error-try-a-different-card-html';
				$messageParams = [
					$this->localizeGlobal( 'OtherWaysURL' ),
					$this->getGlobal( 'ProblemsEmail' )
				];
			} else {
				$messageKey = 'donate_interface-error-msg-' . $field;
				$messageParams = [];
			}
			$localizedErrors[] = new ValidationError(
				$field, $messageKey, $messageParams
			);
			$this->logger->info(
				'createPayment call came back with validation error in ' . $field
			);
		}
		return PaymentResult::newRefresh( $localizedErrors );
	}

	public function getPaymentMethodsSupportingRecurringConversion(): array {
		return [ 'cc' ];
	}
}
