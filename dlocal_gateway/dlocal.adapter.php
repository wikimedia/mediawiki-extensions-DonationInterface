<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

class DlocalAdapter extends GatewayAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	/**
	 * @var string
	 */
	const GATEWAY_NAME = 'Dlocal';

	/**
	 * @var string
	 */
	const IDENTIFIER = 'dlocal';

	/**
	 * @var string
	 */
	const GLOBAL_PREFIX = 'wgDlocalGateway';

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->session_addDonorData();
		$this->setCurrentTransaction( 'authorize' );
		$this->runDoPaymentFilters();
		if ( !$this->filterActionIsProcess() ) {
			// Ensure IPVelocity filter session value is reset on error
			WmfFramework::setSessionValue( Gateway_Extras_CustomFilters_IP_Velocity::RAN_INITIAL, false );
			return $this->newFailureWithError( 'internal-0000', 'Failed pre-process checks for payment.' );
		}

		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->getPaymentMethod() );
		$createPaymentResponse = $this->callCreatePayment( $paymentProvider );
		// We increment the sequence number here, so the next time doPayment is called
		// in the same session we will get a new order ID in ensureUniqueOrderID.
		$this->incrementSequenceNumber();

		if ( count( $createPaymentResponse->getValidationErrors() ) > 0 ) {
			return $this->getLocalizedValidationErrorResult( $createPaymentResponse->getValidationErrors() );
		}

		if ( $createPaymentResponse->requiresRedirect() ) {
			// Add the dLocal-generated transaction ID to the DonationData object
			// to be sent to the queues
			$this->addResponseData( [
				'gateway_txn_id' => $createPaymentResponse->getGatewayTxnId(),
			] );
			// ... and ensure it is persisted in the php session
			$this->session_addDonorData();

			$redirectUrl = $createPaymentResponse->getRedirectUrl();
			$this->logger->info( "Redirecting to $redirectUrl" );
			return PaymentResult::newRedirect( $redirectUrl );
		}

		// Handle fraud checks and any necessary payment capture step here and now.
		return $this->handleCreatedPayment( $createPaymentResponse, $paymentProvider );
	}

	/**
	 * Process the request values sent over with the donor redirect from the dlocal's server.
	 *
	 * We receive a bunch of params in $requestValues. However,
	 * we only care about the payment_id. We use payment_id to look up
	 * the latest status of the payment the donor just made and decide
	 * the next steps based on the status. dLocal advises us to do this.
	 *
	 * @param array $requestValues
	 * @return PaymentResult
	 */
	public function processDonorReturn( $requestValues ): PaymentResult {
		// DLocal currently does not send us the correct return parameters
		// on coming back from recurring UPI (at least in sandbox).
		// In any case, we don't want to send a message to the donations
		// queue from the front-end for recurring UPI because we need to
		// wait for a wallet token that comes in on the IPN listener.
		if ( $this->isIndiaRecurring() ) {
			// Just finalize the donation attempt and send them to the
			// donations queue. Use 'pending' here so the payments-init
			// consumer doesn't delete the message from the pending table.
			$this->finalizeInternalStatus( FinalStatus::PENDING );
			return PaymentResult::newSuccess();
		}

		$paymentMethod = $this->getPaymentMethod();
		if ( $paymentMethod === 'cc' ) {
			// Donor is coming back from a 3dSecure authentication redirect.
			// Sadly, dLocal does not POST back the standard callback_url
			// parameters in this case. Rely on session for transaction ID.
			$gatewayTxnId = $this->getData_Unstaged_Escaped( 'gateway_txn_id' );
		} else {
			// Donor is coming back from one of dLocal's many REDIRECT payment
			// flows. We expect a payment_id on the callback URL.
			// TODO: check signature.
			if ( isset( $requestValues['payment_id'] ) ) {
				$gatewayTxnId = $requestValues['payment_id'];
			} elseif ( $this->getData_Unstaged_Escaped( 'gateway_txn_id' ) ) {
				$this->logger->warning(
					'Expected payment_id parameter in resultSwitcher request, falling back to session'
				);
				$gatewayTxnId = $this->getData_Unstaged_Escaped( 'gateway_txn_id' );
			} else {
				$this->logger->error( "Missing required parameters in request" );
				return $this->newFailureWithError( ErrorCode::MISSING_REQUIRED_DATA, 'Missing required parameters in request' );
			}
		}

		// check the status of the payment the donor just made and processed the result
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $paymentMethod );
		$paymentStatusParams = [ 'gateway_txn_id' => $gatewayTxnId ];
		$paymentStatusResult = $paymentProvider->getLatestPaymentStatus( $paymentStatusParams );
		return $this->handleCreatedPayment( $paymentStatusResult, $paymentProvider );
	}

	public function getCommunicationType() {
		// TODO: Implement getCommunicationType() method.
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
					'phone',
					'state_province',
					'street_address',
					'user_ip',
					'recurring',
					'payment_token',
					'payment_submethod',
					'street_address',
					'street_number',
					'fiscal_number',
					'return_url',
					'use_3d_secure',
					'upi_id',
				],
				'values' => [
					'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' )
				]
			],
			'capture' => [
				'request' => [
					'amount',
					'gateway_txn_id',
					'currency',
					'order_id',
					'upi_id'
				]
			]
		];
	}

	/**
	 * Override parent function to add return supported currencies for current country
	 * @param array $options
	 * @return array
	 */
	public function getCurrencies( $options = [] ): array {
		$country = $options['country'] ?? $this->getData_Unstaged_Escaped( 'country' );

		if ( !$country ) {
			throw new InvalidArgumentException( 'Need to specify country if not yet set in unstaged data' );
		}
		if ( !isset( NationalCurrencies::getNationalCurrencies()[$country] ) ) {
			return [];
		}
		return (array)NationalCurrencies::getNationalCurrencies()[$country];
	}

	/**
	 * Override parent function to add optional phone field for non-recurring UPI
	 * @param array|null $knownData
	 * @return array
	 */
	public function getFormFields( ?array $knownData = null ): array {
		$fields = parent::getFormFields( $knownData );
		if ( $knownData === null ) {
			$knownData = $this->getData_Unstaged_Escaped();
		}
		$isRecurring = !empty( $knownData['recurring'] );
		$isUpi = isset( $knownData['payment_submethod'] ) && $knownData['payment_submethod'] === 'upi';
		if ( $isUpi && !$isRecurring ) {
			$fields['phone'] = 'optional';
		}
		return $fields;
	}

	/**
	 *
	 * @param PaymentDetailResponse $paymentDetailResponse
	 * @param IPaymentProvider $paymentProvider
	 * @return PaymentResult
	 */
	protected function handleCreatedPayment(
		PaymentDetailResponse $paymentDetailResponse,
		IPaymentProvider $paymentProvider
	): PaymentResult {
		$transactionStatus = $paymentDetailResponse->getStatus();
		$this->addCreatePaymentResponseData( $paymentDetailResponse );
		$paymentResult = PaymentResult::newSuccess();

		// Get staged rather than unstaged data to use transformed/generated output
		// from staging helpers (FiscalNumber and PlaceholderFiscalNumber)
		$this->addResponseData( [
			'fiscal_number' => $this->getData_Staged( 'fiscal_number' )
		] );

		if ( !$paymentDetailResponse->isSuccessful() ) {
			$paymentResult = PaymentResult::newFailure();
			$this->logPaymentDetailFailure( $paymentDetailResponse );
			$this->finalizeInternalStatus( $transactionStatus );
		} elseif ( $paymentDetailResponse->requiresApproval() ) {
			$this->runAntifraudFilters();
			if ( !$this->filterActionIsProcess() ) {
				$this->finalizeInternalStatus( FinalStatus::FAILED );
				$paymentResult = PaymentResult::newFailure();
			} else {
				$this->setCurrentTransaction( 'capture' );
				$capturePaymentParams = $this->buildRequestArray();
				$this->logger->info(
					"Calling approvePayment with gateway_txn_id: " . $paymentDetailResponse->getGatewayTxnId()
				);
				$approvePaymentResponse = $paymentProvider->approvePayment( $capturePaymentParams );
				if ( $approvePaymentResponse->isSuccessful() ) {
					$this->addResponseData( [
						'gateway_txn_id' => $approvePaymentResponse->getGatewayTxnId(),
					] );
					// recurring will return a token on the authorization call
					if ( $paymentDetailResponse->getRecurringPaymentToken() ) {
						$this->addResponseData( [
							'recurring_payment_token' => $paymentDetailResponse->getRecurringPaymentToken(),
						] );
						if ( $this->showMonthlyConvert() ) {
							$this->session_addDonorData();
						}
					}
				}
				$this->finalizeInternalStatus( $approvePaymentResponse->getStatus() );
			}
		} else {
			$this->finalizeInternalStatus( $transactionStatus );
		}

		// Run some post-donation filters and send donation queue message
		$this->postProcessDonation();
		return $paymentResult;
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
						'dlocal',
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

	protected function defineOrderIDMeta() {
		$this->order_id_meta = [
			'ct_id' => true,
			'generate' => true,
		];
	}

	protected function defineAccountInfo() {
		$this->accountInfo = $this->account_config;
	}

	protected function defineReturnValueMap() {
		// TODO: Implement defineReturnValueMap() method.
	}

	/**
	 * @param IPaymentProvider $paymentProvider
	 * @return CreatePaymentResponse
	 */
	protected function callCreatePayment( IPaymentProvider $paymentProvider ): CreatePaymentResponse {
		$createPaymentParams = $this->buildRequestArray();
		$this->logger->info( "Calling createPayment for Dlocal payment" );
		// If we are going to ask for a monthly donation after a one-time donation completes, set the
		// recurring param to 1 to tokenize the payment.
		if ( $this->showMonthlyConvert() ) {
			$createPaymentParams['recurring'] = 1;
		}
		$createPaymentResponse = $paymentProvider->createPayment( $createPaymentParams );
		if ( !empty( $createPaymentResponse->getGatewayTxnId() ) ) {
			$this->logger->info( "Returned Authorization ID {$createPaymentResponse->getGatewayTxnId()}" );
		}

		return $createPaymentResponse;
	}

	/**
	 * @return void
	 */
	protected function runDoPaymentFilters(): void {
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
	 * @param PaymentDetailResponse $paymentDetailResponse
	 * @return void
	 */
	protected function logPaymentDetailFailure( PaymentDetailResponse $paymentDetailResponse ): void {
		$errorLogMessage = 'Unsuccessful createPayment response from gateway: ';
		$errorLogMessage .= $paymentDetailResponse->getStatus() . " : ";
		$rawResponse = $paymentDetailResponse->getRawResponse();
		if ( isset( $rawResponse['card'] ) ) {
			unset( $rawResponse['card'] );
		}
		$errorLogMessage .= json_encode( $rawResponse );
		$this->logger->info( $errorLogMessage );
	}

	public function getPaymentMethodsSupportingRecurringConversion(): array {
		return [ 'cc' ];
	}

	protected function isIndiaRecurring(): bool {
		return ( in_array(
				$this->getPaymentSubmethod(), [ 'upi', 'paytmwallet' ], true
			) ) &&
			$this->getData_Unstaged_Escaped( 'recurring' );
	}

	protected function addCreatePaymentResponseData( PaymentDetailResponse $paymentDetailResponse ): void {
		$data = [
			'gateway_txn_id' => $paymentDetailResponse->getGatewayTxnId()
		];
		if ( $paymentDetailResponse->getPaymentSubmethod() ) {
			$data['payment_submethod'] = $paymentDetailResponse->getPaymentSubmethod();
		}
		$this->addResponseData( $data );
	}

	public function normalizeOrderID( $override = null, $dataObj = null ) {
		$orderId = parent::normalizeOrderID( $override, $dataObj );
		if ( !$dataObj ) {
			$dataObj = $this->dataObj;
		}
		$contributionTrackingId = $dataObj->getVal( 'contribution_tracking_id' );
		if ( !str_starts_with( $orderId, $contributionTrackingId ) ) {
			$mismatchedId = $orderId;
			$orderId = $this->generateOrderID( $dataObj );
			$this->setOrderIDMeta( 'final', $orderId );
			$this->setOrderIDMeta( 'final_source', 'generated' );
			$this->logger->warning( "Found mismatched old order ID $mismatchedId, regenerated new ID $orderId." );
		}
		return $orderId;
	}

	protected function getQueueDonationMessage(): array {
		$message = parent::getQueueDonationMessage();
		return $message;
	}
}
