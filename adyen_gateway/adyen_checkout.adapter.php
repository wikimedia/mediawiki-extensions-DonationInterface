<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

class AdyenCheckoutAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	public const GATEWAY_NAME = 'AdyenCheckout';
	public const IDENTIFIER = 'adyen';
	public const GLOBAL_PREFIX = 'wgAdyenCheckoutGateway';

	public function doPayment(): PaymentResult {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		// createPayment = Authorize, in the credit card world.
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
		$createPaymentParams = $this->buildRequestArray();

		// If we are going to ask for a monthly donation after a one-time donation completes, set the
		// recurring param to 1 to tokenize the payment.
		if ( $this->showMonthlyConvert() ) {
			$createPaymentParams['recurring'] = 1;
			// Since we're not sure if we're going to ever use the token, flag the transaction as
			// 'card on file' rather than 'subscription' (the default for recurring). This may avoid
			// donor complaints of one-time donations appearing as recurring on their card statement.
			$createPaymentParams['recurring_model'] = RecurringModel::CARD_ON_FILE;
		}
		$this->logger->info( "Calling createPayment for {$createPaymentParams['email']}" );
		$createPaymentResponse = $provider->createPayment( $createPaymentParams );
		$this->logger->info( "Returned PSP Reference {$createPaymentResponse->getGatewayTxnId()}" );
		$validationErrors = $createPaymentResponse->getValidationErrors();
		// If there are validation errors, present them for correction with a
		// 'refresh' type PaymentResult
		if ( count( $validationErrors ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $validationErrors );
		}
		if ( $createPaymentResponse->requiresRedirect() ) {
			// Looks like we're not going to finish the payment in this
			// request - our dear donor needs to take more actions on
			// another site. Short-circuit the finalization, just stash
			// the gateway txn id and redirect them.
			$this->addResponseData( [
				'gateway_txn_id' => $createPaymentResponse->getGatewayTxnId()
			] );
			$redirectUrl = $createPaymentResponse->getRedirectUrl();
			$this->logger->info( "Redirecting to $redirectUrl" );
			return PaymentResult::newRedirect(
				$redirectUrl,
				$createPaymentResponse->getRedirectData()
			);
		}
		// If we DON'T need to redirect, handle the fraud checks and any
		// necessary payment capture step here and now.
		return $this->handleCreatedPayment( $provider, $createPaymentResponse );
	}

	/**
	 * After a payment has been created and we have the processor-side fraud results
	 * (AVS & CVV checks), run our fraud filters and capture the payment if needed.
	 *
	 * @param IPaymentProvider $provider
	 * @param PaymentProviderExtendedResponse $createPaymentResponse
	 *
	 * @return PaymentResult
	 */
	protected function handleCreatedPayment(
		IPaymentProvider $provider, PaymentProviderExtendedResponse $createPaymentResponse
	): PaymentResult {
		$transactionStatus = $createPaymentResponse->getStatus();
		$responseData = [
			'gateway_txn_id' => $createPaymentResponse->getGatewayTxnId()
		];
		if ( !$this->getPaymentSubmethod() ) {
			$responseData['payment_submethod'] = $createPaymentResponse->getPaymentSubmethod() ?? '';
		}

		if ( !$this->getPaymentMethod() ) {
			$responseData['payment_method'] = $createPaymentResponse->getPaymentMethod();
		}
		$this->addResponseData( $responseData );
		// When authorization is successful but capture fails (or is not
		// attempted because our ValidationAction is 'review', we still
		// send the donor to the Thank You page. This is because the
		// donation can still be captured manually by Donor Relations and
		// we don't want the donor to try again.
		$paymentResult = PaymentResult::newSuccess();
		if ( !$createPaymentResponse->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			// TODO: map any errors from $approvePaymentResponse
			// log the error details on failure
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $createPaymentResponse->getStatus() . " : ";
			$errorLogMessage .= json_encode( $createPaymentResponse->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		} elseif ( $createPaymentResponse->requiresApproval() ) {
			$this->runFraudFiltersIfNeeded( $createPaymentResponse );
			switch ( $this->getValidationAction() ) {
				case ValidationAction::PROCESS:
					$this->logger->info( "Calling approvePayment on PSP reference {$createPaymentResponse->getGatewayTxnId()}" );
					$approvePaymentResponse = $provider->approvePayment( [
						// Note that approvePayment takes the unstaged amount
						'amount' => $this->getData_Unstaged_Escaped( 'amount' ),
						'currency' => $this->getData_Staged( 'currency' ),
						'gateway_txn_id' => $createPaymentResponse->getGatewayTxnId(),
					] );
					$transactionStatus = $approvePaymentResponse->getStatus();
					if ( $approvePaymentResponse->isSuccessful() ) {
						// Note: this transaction ID is different from the approvePaymentResponse's
						// transaction ID. We log this, but leave the gateway_txn_id set to
						// the ID from approvePaymentResponse as that is what we get in the IPN.
						$this->logger->info( "Returned PSP Reference {$approvePaymentResponse->getGatewayTxnId()}" );
						if ( $this->showMonthlyConvert() ) {
							$this->logger->info( "Displaying monthly convert modal" );
							$paymentResult = PaymentResult::newSuccess();
						}
					} else {
						$this->logger->info( 'Capture call unsuccessful' );
					}
					break;
				case ValidationAction::REJECT:
					// If the payment was rejected still throw a regular thank-you page to avoid confuse donor
					// since we might still capture the payment later by pending transaction resolver. T394098
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
		$recurringToken = $createPaymentResponse->getRecurringPaymentToken();
		if ( $recurringToken ) {
			$this->addResponseData( [
				'recurring_payment_token' => $recurringToken,
				'processor_contact_id' => $createPaymentResponse->getProcessorContactID()
			] );
			if ( $this->showMonthlyConvert() ) {
				$this->session_addDonorData();
			}
		} elseif ( $this->getData_Unstaged_Escaped( 'recurring' ) && $createPaymentResponse->isSuccessful() ) {
			// For recurring iDEAL or SEPA payments the recurring_payment_token comes in later on a RECURRING_CONTRACT Webhook/IPN
			// message, and we require this value to push a *complete* recurring donation message
			// to the queue.
			if ( $this->isRecurringBankPayment() ) {
				$this->logger->info( $responseData['gateway_txn_id'] . ': Recurring token will pass later from RECURRING_CONTRACT IPN Message' );
			} else {
				$this->logger->warning( $responseData['gateway_txn_id'] . ': No token found on successful recurring payment authorization response.' );
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
					'recurring',
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
			case PaymentMethod::PAYMENT_METHOD_DIRECT_DEBIT:
				$this->transactions['authorize']['request'] =
					array_merge( $this->transactions['authorize']['request'], [
						'supplemental_address_1',
						'payment_submethod',
						'encrypted_bank_account_number',
						'encrypted_bank_location_id',
						'bank_account_type',
						'full_name'
					] );
				break;
			case PaymentMethod::PAYMENT_METHOD_CREDIT_CARD:
				$this->transactions['authorize']['request']['encrypted_payment_data'] = [
					'encryptedCardNumber',
					'encryptedExpiryMonth',
					'encryptedExpiryYear',
					'encryptedSecurityCode'
				];
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
				break;
			case PaymentMethod::PAYMENT_METHOD_REAL_TIME_BANK_TRANSFER:
				switch ( $this->getPaymentSubmethod() ) {
					case PaymentMethod::PAYMENT_SUBMETHOD_ONLINE_BANKING_CZ:
						$this->transactions['authorize']['request'][] = 'issuer_id';
						break;
					case PaymentMethod::PAYMENT_SUBMETHOD_IDEAL_BANK_TRANSFER:
						$this->transactions['authorize']['request'][] = 'payment_submethod';
						break;
					case PaymentMethod::PAYMENT_SUBMETHOD_SEPA_DIRECT_DEBIT:
						$this->transactions['authorize']['request'] =
							array_merge( $this->transactions['authorize']['request'], [
								'iban',
								'full_name'
							] );
						break;
				}
				break;
			case PaymentMethod::PAYMENT_METHOD_BANK_TRANSFER:
				$this->transactions['authorize']['request'][] = 'issuer_id';
				break;
			case PaymentMethod::PAYMENT_METHOD_GOOGLEPAY:
			case PaymentMethod::PAYMENT_METHOD_APPLEPAY:
				$this->transactions['authorize']['request'][] = 'payment_token';
		}
	}

	protected function defineAccountInfo() {
		// We use account_config instead
		$this->accountInfo = [];
	}

	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [ 'request' => 'merchantReference' ],
			'ct_id' => true,
			'generate' => true,
		];
	}

	/** @inheritDoc */
	public function getRequiredFields( $knownData = null ) {
		$fields = parent::getRequiredFields( $knownData );
		return array_diff( $fields, $this->getFieldsToRemove( $knownData ) );
	}

	/** @inheritDoc */
	public function getFormFields( ?array $knownData = null ): array {
		$fields = parent::getFormFields( $knownData );
		return array_diff_key(
			$fields,
			array_fill_keys( $this->getFieldsToRemove( $knownData ), true )
		);
	}

	public function getGoogleAllowedNetworks(): array {
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

	protected function getFieldsToRemove( ?array $knownData = null ): array {
		$method = $knownData['payment_method'] ?? $this->getData_Unstaged_Escaped( 'payment_method' );
		$submethod = $knownData['payment_submethod'] ?? $this->getData_Unstaged_Escaped( 'payment_submethod' );
		if ( $method === PaymentMethod::PAYMENT_METHOD_APPLEPAY ) {
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
		} elseif ( $method === PaymentMethod::PAYMENT_METHOD_GOOGLEPAY ) {
			return [
				'street_address',
				'postal_code',
				'city'
			];
		} elseif (
			$method === PaymentMethod::PAYMENT_METHOD_DIRECT_DEBIT ||
			( $method === PaymentMethod::PAYMENT_METHOD_REAL_TIME_BANK_TRANSFER
				&& $submethod === PaymentMethod::PAYMENT_SUBMETHOD_SEPA_DIRECT_DEBIT ) ) {
			return [
				'first_name',
				'last_name',
				'street_address',
				'postal_code',
				'city'
			];
		}
		return [];
	}

	public function getCheckoutConfiguration(): array {
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		'@phan-var PaymentProvider $provider';
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
				$this->logger->warning( "paymentMethod lookup error: {$error->getDebugMessage()}" );
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
			'script' => $this->getAccountConfig( 'Script' ),
			'googleScript' => $this->getAccountConfig( 'GoogleScript' ),
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

	/** @inheritDoc */
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
			'@phan-var PaymentProvider $provider';
			$latestPaymentDetailsResponse = $provider->getHostedPaymentDetails( $redirectResult );
			$this->logger->debug(
				'Hosted payment detail response: ' . json_encode( $latestPaymentDetailsResponse->getRawResponse() )
			);
			return $this->handleCreatedPayment( $provider, $latestPaymentDetailsResponse );
		}
		// Default behavior is to finalize and return success
		return parent::processDonorReturn( $requestValues );
	}

	/**
	 * Adds a catch to stop recurring iDEALs and sepa from being sent to the donations queue
	 * but otherwise sends to the queues as normal
	 *
	 * @param string $queue What queue to send the message to
	 * @param bool $contactOnly If we only have the donor's contact information
	 */
	protected function pushMessage( $queue, $contactOnly = false ) {
		// Don't send recurring bank payments to the donations queue.
		// These are pushed later via the IPN listener.
		if ( $queue === 'donations' && $this->isRecurringBankPayment() ) {
			return;
		}

		// Send all other adyen messages to the queues as normal
		parent::pushMessage( $queue, $contactOnly );
	}

	/**
	 * We don't send recurring iDEAL or SEPA payments to the donations queue after a successful payment.
	 * For these recurring payments, the recurring_payment_token comes in on a RECURRING_CONTRACT Webhook/IPN message
	 * , and we require this value to push a *complete* recurring donation message to the queue.
	 * Instead, the SmashPig listener pushes them to the donations queue once it receives recurring_payment_token
	 * within the IPN message.
	 *
	 * @return bool
	 */
	private function isRecurringBankPayment(): bool {
		$bankPaymentSubMethods = [ PaymentMethod::PAYMENT_SUBMETHOD_IDEAL_BANK_TRANSFER,
			PaymentMethod::PAYMENT_SUBMETHOD_SEPA_DIRECT_DEBIT ];
		$isBankPaymentSubMethods = in_array( $this->getPaymentSubmethod(), $bankPaymentSubMethods );
		$isRecurring = $this->getData_Unstaged_Escaped( 'recurring' );

		return $isBankPaymentSubMethods && $isRecurring;
	}

	/**
	 * Runs antifraud filters if the appropriate for the current payment method.
	 * Sets $this->action to one of the ValidationAction constants.
	 *
	 * @param PaymentProviderExtendedResponse $createPaymentResponse
	 */
	protected function runFraudFiltersIfNeeded( PaymentProviderExtendedResponse $createPaymentResponse ): void {
		$riskScores = $createPaymentResponse->getRiskScores();
		if ( $this->getPaymentMethod() === PaymentMethod::PAYMENT_METHOD_CREDIT_CARD ) {
			$this->addResponseData( [
				'cvv_result' => $riskScores['cvv'] ?? 0,
				'avs_result' => $riskScores['avs'] ?? 0
			] );
		} else {
			$this->logger->info(
				'payment method is not credit card, set avs_result and cvv_result to 0 as cvv does not apply'
			);
			$this->addResponseData( [ 'avs_result' => 0, 'cvv_result' => 0 ] );
		}
		$this->runAntifraudFilters();
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
		return [ PaymentMethod::PAYMENT_METHOD_CREDIT_CARD,
			PaymentMethod::PAYMENT_METHOD_GOOGLEPAY,
			PaymentMethod::PAYMENT_METHOD_APPLEPAY,
			PaymentMethod::PAYMENT_METHOD_DIRECT_DEBIT ];
	}

	/**
	 * Override parent function to set specific message replacements for Japanese
	 *
	 * @param string|null $key
	 * @return array|mixed|null
	 */
	public function getConfig( $key = null ) {
		if ( $key === 'message_replacements' && $this->getData_Unstaged_Escaped( 'language' ) === 'ja' ) {
			return $this->config['message_replacements_ja'];
		}
		return parent::getConfig( $key );
	}
}
