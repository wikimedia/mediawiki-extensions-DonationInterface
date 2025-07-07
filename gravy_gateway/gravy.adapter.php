<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\Address;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

class GravyAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	/**
	 * @var string
	 */
	public const GATEWAY_NAME = 'Gravy';

	/**
	 * @var string
	 */
	public const IDENTIFIER = 'gravy';

	/**
	 * @var string
	 */
	public const GLOBAL_PREFIX = 'wgGravyGateway';

	/**
	 * @inheritDoc
	 */
	protected function defineTransactions() {
		$this->transactions = [
			'authorize' => [
				'request' => [
					'city',
					'country',
					'currency',
					'email',
					'first_name',
					'last_name',
					'postal_code',
					'phone',
					'state_province',
					'street_address',
					'street_number',
					'fiscal_number',
					'amount',
					'order_id',
					'user_ip',
					'recurring',
					'payment_method',
					'payment_submethod',
					'user_name',
					'description',
					'return_url',
					'use_3d_secure',
					'gateway_session_id',
					'card_suffix',
					'card_scheme',
					'payment_token',
					'full_name'
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
				]
			],
			'capture' => [
				'request' => [
					'amount',
					'currency',
					'gateway_txn_id'
				]
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doPayment(): PaymentResult {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->runPaymentFilters();
		if ( !$this->filterActionIsProcess() ) {
			// Ensure IPVelocity filter session value is reset on error
			WmfFramework::setSessionValue( Gateway_Extras_CustomFilters_IP_Velocity::RAN_INITIAL, false );
			return $this->newFailureWithError( 'internal-0000', 'Failed pre-process checks for payment.' );
		}

		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->getPaymentMethod() );

		$this->setCurrentTransaction( 'authorize' );

		if ( !$this->paymentMethodSupportsRecurring() ) {
			$this->addResponseData( [
				'recurring' => '',
			] );
			// rerun staging helpers with new value (importantly, ReturnUrl helper)
			$this->stageData();
		}
		$createPaymentResponse = $this->callCreatePayment( $paymentProvider );
		// We increment the sequence number here, so the next time doPayment is called
		// in the same session we will get a new order ID in ensureUniqueOrderID.
		$this->incrementSequenceNumber();

		if ( count( $createPaymentResponse->getValidationErrors() ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $createPaymentResponse->getValidationErrors() );
		}

		// Add the gravy-generated transaction ID to the DonationData object
		// to be sent to the queues
		$this->updateResponseData( $createPaymentResponse );

		if ( $createPaymentResponse->requiresRedirect() ) {
			// ... and ensure it is persisted in the php session
			$this->session_addDonorData();

			$redirectUrl = $createPaymentResponse->getRedirectUrl();
			$this->logger->info( "Redirecting to $redirectUrl" );
			return PaymentResult::newRedirect( $redirectUrl );
		}

		return $this->handleCreatedPayment( $paymentProvider, $createPaymentResponse );
	}

	public function getPaymentMethodsSupportingRecurringConversion(): array {
		return [ 'cc' ];
	}

	public function paymentMethodSupportsRecurring(): bool {
		return $this->payment_methods[$this->getPaymentMethod()]['recurring'];
	}

	public function getGravyConfiguration(): array {
		return [
			'gravyID' => $this->getAccountConfig( 'gravyID' ),
			'locale' => str_replace( '_', '-', $this->getData_Staged( 'language' ) ),
			'environment' => $this->getAccountConfig( 'environment' ),
			'googleEnvironment' => $this->getAccountConfig( 'googleEnvironment' ),
			'merchantAccountID' => $this->getAccountConfig( 'merchantAccountID' ),
			'gravyGooglePayMerchantId' => $this->getAccountConfig( 'gravyGooglePayMerchantId' ),
			'googleMerchantId' => $this->getAccountConfig( 'GoogleMerchantId' ),
			'googleAllowedNetworks' => $this->getGoogleAllowedNetworks(),
			'secureFieldsJsScript' => $this->getAccountConfig( 'secureFieldsJS' ),
			'secureFieldsCSS' => $this->getAccountConfig( 'secureFieldsCSS' ),
			'googleScript' => $this->getAccountConfig( 'GoogleScript' ),
			'appleScript' => $this->getAccountConfig( 'AppleScript' ),
		];
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

	/** @inheritDoc */
	public function processDonorReturn( $requestValues ): PaymentResult {
		$this->logger->info( "Handling redirectResult " . json_encode( $requestValues ) );
		$provider = PaymentProviderFactory::getProviderForMethod(
			$this->getPaymentMethod()
		);
		'@phan-var \SmashPig\PaymentProviders\IPaymentProvider $provider';

		$mappedResult = [];
		if ( isset( $requestValues['transaction_id'] ) ) {
			$mappedResult['gateway_txn_id'] = $requestValues['transaction_id'];
		} elseif ( isset( $requestValues['gr4vy_transaction_id'] ) ) {
			$mappedResult['gateway_txn_id'] = $requestValues['gr4vy_transaction_id'];
		}

		// @phan-suppress-next-line PhanUndeclaredMethod get Payment details is only declared in the gravy provider
		$latestPaymentResponse = $provider->getLatestPaymentStatus( $mappedResult );

		$this->logger->debug(
			'Gravy donor return response: ' . json_encode( $latestPaymentResponse->getRawResponse() )
		);

		return $this->handleCreatedPayment( $provider, $latestPaymentResponse );
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

		// Ensure required DonationData information are filled
		$this->updateResponseData( $createPaymentResponse );

		// When authorization is successful but capture fails (or is not
		// attempted because our ValidationAction is 'review', we still
		// send the donor to the Thank You page. This is because the
		// donation can still be captured manually by Donor Relations and
		// we don't want the donor to try again.
		$paymentResult = PaymentResult::newSuccess();
		if ( !$createPaymentResponse->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			// TODO: map any errors from $createPaymentResponse
			// log the error details on failure
			$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
			$errorLogMessage .= $createPaymentResponse->getStatus() . " : ";
			$errorLogMessage .= json_encode( $createPaymentResponse->getRawResponse() );
			$this->logger->info( $errorLogMessage );
		} elseif ( $createPaymentResponse->requiresApproval() ) {
			$this->runFraudFilters( $createPaymentResponse );
			switch ( $this->getValidationAction() ) {
				case ValidationAction::PROCESS:
					// do approve payment request here.
					$this->setCurrentTransaction( 'capture' );
					$this->logger->info( "Calling approvePayment on PSP reference {$createPaymentResponse->getGatewayTxnId()}" );
					$approvePaymentResponse = $this->callApprovePayment( $provider );
					$transactionStatus = $approvePaymentResponse->getStatus();
					$this->updateResponseData( $approvePaymentResponse );
					if ( $approvePaymentResponse->isSuccessful() ) {
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
					$this->logger->info( "Logging rejected payment response for audit.",
						$createPaymentResponse->getRawResponse()
					);
					// If the payment was rejected still throw a regular thank-you page to avoid confuse donor
					// since we might still capture the payment later by pending transaction resolver. T394098
					$paymentResult = PaymentResult::newSuccess();
					$this->logger->info( 'Created payment rejected by our fraud filters' );
					break;
				default:
					$this->logger->info(
						'Not capturing authorized payment - validation action is ' .
						$this->getValidationAction()
					);
			}
		}

		if ( $createPaymentResponse->isSuccessful() ) {
			// save donor data for recur conversion
			if ( $createPaymentResponse->getRecurringPaymentToken() && $this->showMonthlyConvert() ) {
				$this->session_addDonorData();
			}
		}

		// Log and send the payments-init message, and clean out the session
		$this->finalizeInternalStatus( $transactionStatus );

		$this->postProcessDonation();
		return $paymentResult;
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

	/**
	 * Runs antifraud filters if the appropriate for the current payment method.
	 * Sets $this->action to one of the ValidationAction constants.
	 *
	 * @param PaymentProviderExtendedResponse $createPaymentResponse
	 */
	protected function runFraudFilters( PaymentProviderExtendedResponse $createPaymentResponse ): void {
		$riskScores = $createPaymentResponse->getRiskScores();
		if ( $this->getPaymentMethod() === PaymentMethod::PAYMENT_METHOD_CREDIT_CARD ) {
			$this->addResponseData( [
				'cvv_result' => $riskScores['cvv'] ?? 0,
				'avs_result' => $riskScores['avs'] ?? 0
			] );
		} else {
			$this->logger->info( "if the payment method is not credit card, set avs_result and cvv_result to 0 as cvv does not apply" );
			$this->addResponseData( [
				'cvv_result' => 0,
				'avs_result' => 0
			] );
		}

		$this->runAntifraudFilters();
	}

	/**
	 * @return void
	 */
	protected function runPaymentFilters(): void {
		Gateway_Extras_CustomFilters::onGatewayReady( $this );
		$this->runSessionVelocityFilter();
	}

	/**
	 * @return bool
	 */
	protected function filterActionIsProcess(): bool {
		return $this->getValidationAction() === ValidationAction::PROCESS;
	}

	/**
	 * @param string $errorCode
	 * @param string $debugMessage
	 * @return PaymentResult
	 */
	protected function newFailureWithError( $errorCode, $debugMessage ): PaymentResult {
		$paymentError = new PaymentError(
			$errorCode,
			$debugMessage,
			LogLevel::INFO
		);
		return PaymentResult::newFailure( [ $paymentError ] );
	}

	/**
	 * @param IPaymentProvider $paymentProvider
	 * @return CreatePaymentResponse
	 */
	protected function callCreatePayment( IPaymentProvider $paymentProvider ): CreatePaymentResponse {
		$this->tuneForPaymentMethod();
		$createPaymentParams = $this->buildRequestArray();
		$this->fixBrowserInfoIfPresent( $createPaymentParams );
		if ( $this->showMonthlyConvert() ) {
			$createPaymentParams['recurring'] = 1;
			$createPaymentParams['recurring_model'] = RecurringModel::CARD_ON_FILE;
		}
		$this->logger->info( "Calling createPayment for Gravy payment" );

		$createPaymentResponse = $paymentProvider->createPayment( $createPaymentParams );
		if ( $createPaymentResponse->getGatewayTxnId() !== null ) {
			$this->logger->info( "Returned Authorization ID {$createPaymentResponse->getGatewayTxnId()}" );
		}

		return $createPaymentResponse;
	}

	/**
	 * @param IPaymentProvider $paymentProvider
	 * @return ApprovePaymentResponse
	 */
	protected function callApprovePayment( IPaymentProvider $paymentProvider ): ApprovePaymentResponse {
		$approvePaymentParams = $this->buildRequestArray();
		$this->logger->info( "Calling approvePayment for Gravy payment" );

		$approvePaymentResponse = $paymentProvider->approvePayment( $approvePaymentParams );
		if ( $approvePaymentResponse->getGatewayTxnId() !== null ) {
			$this->logger->info( "Returned Captured ID {$approvePaymentResponse->getGatewayTxnId()}" );
		}

		return $approvePaymentResponse;
	}

	public function getCheckoutSession(): CreatePaymentSessionResponse {
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->getPaymentMethod() );
		// @phan-suppress-next-line PhanUndeclaredMethod the createPaymentSession variable is declared in Gravy Payment Provider class but not on the general interface
		$response = $paymentProvider->createPaymentSession();
		if ( !$response->isSuccessful() ) {
			$this->logger->error( 'Create payment session call unsuccessful. Raw response: ' . $response->getRawResponse() );
		}
		return $response;
	}

	/**
	 * @inheritDoc
	 */
	protected function defineAccountInfo() {
		// TODO: Implement defineAccountInfo() method.
		$this->accountInfo = $this->account_config;
	}

	/**
	 * @inheritDoc
	 */
	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'ct_id' => true,
			'generate' => true,
		];
	}

		/**
		 * @param ValidationError[] $validationErrors
		 * @return PaymentResult
		 */
	protected function getLocalizedValidationErrorResult( array $validationErrors ): PaymentResult {
		$localizedErrors = [];
		foreach ( $validationErrors as $error ) {
			$field = $error->getField();
			$debugMessage = $error->getDebugMessage();
			if ( $field === 'payment_token' ) {
				// This means the generated token was invalid.
				$urlParameterKeys = [
					'payment_method',
					'recurring',
					'uselang',
					'language',
					'currency',
					'amount',
					'country',
					'utm_source',
					'utm_medium',
					'utm_campaign'
				];
				$urlParameters = [];
				foreach ( $urlParameterKeys as $key ) {
					if ( isset( $this->unstaged_data[$key] ) ) {
						$urlParameters[$key] = $this->unstaged_data[$key];
					}
				}
				$messageKey = 'donate_interface-donate-error-try-again-html';
				$messageParams = [
					GatewayChooser::buildGatewayPageUrl(
						'gravy',
						$urlParameters,
						MediaWikiServices::getInstance()->getMainConfig()
					),
					$this->localizeGlobal( 'OtherWaysURL' ),
					self::getGlobal( 'ProblemsEmail' )
				];
			} else {
				if ( in_array( $field, [ 'currency', 'fiscal_number' ] ) ) {
					$messageKey = "donate_interface-error-msg-invalid-$field";
				} else {
					$messageKey = 'donate_interface-error-msg-' . $field;
				}
				$messageParams = [];
			}
			$localizedErrors[] = new ValidationError(
				$field, $messageKey, $messageParams
			);
			$this->logger->info(
				'createPayment call came back with validation error in ' . $field . ( $debugMessage
					? ' with message: ' . $debugMessage : '' )
			);
		}
		return PaymentResult::newRefresh( $localizedErrors );
	}

	protected function updateResponseData( PaymentProviderExtendedResponse $paymentResult ): void {
		$responseData = [];

		// Add the gravy-generated transaction ID to the DonationData object
		// to be sent to the queues
		if ( $paymentResult->isSuccessful() ) {
			$responseData['gateway_txn_id'] = $paymentResult->getGatewayTxnId();
			$responseData['backend_processor'] = $paymentResult->getBackendProcessor();
			$responseData['backend_processor_txn_id'] = $paymentResult->getBackendProcessorTransactionId();
			// Add in donor details
			if ( $paymentResult->getDonorDetails() !== null ) {
				if ( $this->isNotEmptyOrNull( $paymentResult->getDonorDetails()->getEmail() ) ) {
					$responseData['email'] = $paymentResult->getDonorDetails()->getEmail();
				}
				if ( $this->isNotEmptyOrNull( $paymentResult->getDonorDetails()->getFirstName() ) ) {
					$responseData['first_name'] = $paymentResult->getDonorDetails()->getFirstName();
				}
				if ( $this->isNotEmptyOrNull( $paymentResult->getDonorDetails()->getLastName() ) ) {
					$responseData['last_name'] = $paymentResult->getDonorDetails()->getLastName();
				}
				if ( $this->isNotEmptyOrNull( $paymentResult->getDonorDetails()->getBillingEmail() ) ) {
					$responseData['billing_email'] = $paymentResult->getDonorDetails()->getBillingEmail();
				}
				// Add in billing details, if Paypal shipping details are needed there is a toggle in the gravy console
				$billingAddress = $paymentResult->getDonorDetails()->getBillingAddress();
				if ( $billingAddress instanceof Address ) {
					if ( $this->isNotEmptyOrNull( $billingAddress->getStreetAddress() ) ) {
						$responseData['street_address'] = $billingAddress->getStreetAddress();
					}
					if ( $this->isNotEmptyOrNull( $billingAddress->getCity() ) ) {
						$responseData['city'] = $billingAddress->getCity();
					}
					if ( $this->isNotEmptyOrNull( $billingAddress->getStateOrProvinceCode() ) ) {
						$responseData['state_province'] = $billingAddress->getStateOrProvinceCode();
					}
					if ( $this->isNotEmptyOrNull( $billingAddress->getPostalCode() ) ) {
						$responseData['postal_code'] = $billingAddress->getPostalCode();
					}
					if ( $this->isNotEmptyOrNull( $billingAddress->getCountryCode() ) ) {
						$responseData['country'] = $billingAddress->getCountryCode();
					}
				}
				// Add in username if it's there
				if ( $this->isNotEmptyOrNull( $paymentResult->getDonorDetails()->getUserName() ) ) {
					$responseData['user_name'] = $paymentResult->getDonorDetails()->getUserName();
				}
			}
			if ( $paymentResult->getPaymentOrchestratorReconciliationId() ) {
				$responseData['payment_orchestrator_reconciliation_id'] = $paymentResult->getPaymentOrchestratorReconciliationId();
			}

			if ( $paymentResult->getRecurringPaymentToken() != null ) {
				$responseData['recurring_payment_token'] = $paymentResult->getRecurringPaymentToken();
			} elseif ( $this->getData_Unstaged_Escaped( 'recurring' ) && $paymentResult->isSuccessful() ) {
				$this->logger->warning( 'No token found on successful recurring payment authorization response.' );
			}

			if ( $paymentResult->getProcessorContactID() != null ) {
				$responseData['processor_contact_id'] = $paymentResult->getProcessorContactID();
			}
			if ( !$this->getPaymentSubmethod() ) {
				$responseData['payment_submethod'] = $paymentResult->getPaymentSubmethod() ?? '';
			}
			if ( !$this->getPaymentMethod() ) {
				$responseData['payment_method'] = $paymentResult->getPaymentMethod();
			}
		}

		$this->addResponseData( $responseData );
	}

	protected function getQueueDonationMessage(): array {
		$message = parent::getQueueDonationMessage();
		// save external_identifier as gravy buyer id
		if ( isset( $this->unstaged_data['processor_contact_id'] ) ) {
			$message['external_identifier'] = $this->unstaged_data['processor_contact_id'];
		}

		if ( isset( $this->unstaged_data['user_name'] ) ) {
			$message['external_identifier'] = $this->unstaged_data['user_name'];
		}

		return $message;
	}

	/**
	 * To handle !empty checks, we don't want to put empty params onto the queue
	 * https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP#empty()
	 *
	 * @param ?string $value
	 * @return bool
	 */
	protected function isNotEmptyOrNull( $value ) {
		if ( $value !== null && $value !== '' ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Add 3dSecure keys to the authorize array when paying with credit card
	 * @return void
	 */
	protected function tuneForPaymentMethod(): void {
		if ( $this->getPaymentMethod() === PaymentMethod::PAYMENT_METHOD_CREDIT_CARD ) {
			$this->transactions['authorize']['request']['browser_info'] = [
				'user_agent',
				'accept_header',
				'language',
				'color_depth',
				'screen_height',
				'screen_width',
				'time_zone_offset',
				'javascript_enabled',
				'user_device',
			];
			$this->transactions['authorize']['request'][] = 'window_origin';
			// Always true, as we don't accept donations w/o javascript
			$this->transactions['authorize']['values']['javascript_enabled'] = true;
		}
	}

	/**
	 * Called after building the transaction array, to add a hard-coded 'false' value
	 * @param array &$requestParams
	 * @return void
	 */
	protected function fixBrowserInfoIfPresent( array &$requestParams ): void {
		if ( isset( $requestParams['browser_info'] ) ) {
			// We are unable to set a hard-coded 'false' value in the transaction 'values' array as we would prefer,
			// due to a quirk of the buildRequestArray logic.
			$requestParams['browser_info']['java_enabled'] = false;
		}
	}

}
