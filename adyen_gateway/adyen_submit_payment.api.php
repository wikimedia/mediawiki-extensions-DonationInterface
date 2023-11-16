<?php
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\SequenceGenerators;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use Wikimedia\ParamValidator\ParamValidator;

class AdyenSubmitPaymentApi extends ApiBase {
	private const GATEWAY = 'adyen';
	private const STATUS_ERROR = 'error';
	private const STATUS_SUCCESS = 'success';
	// For payments init, always process as we aren't doing fraud checks here
	private const VALIDATION_ACTION = 'process';

	/**
	 * @var string
	 */
	public string $contributionTrackingId;

	/**
	 * @var array
	 */
	public array $donationData;

	/**
	 * @var string
	 */
	public string $gateway = self::GATEWAY;

	/**
	 * @var string
	 */
	public string $gatewayAccount;

	/**
	 * @var string
	 */
	public string $gatewayTransactionId;

	/**
	 * @var string
	 */
	public string $orderId;

	public function execute() {
		if ( RequestContext::getMain()->getUser()->pingLimiter( 'submitpayment' ) ) {
			// Allow rate limiting by setting e.g. $wgRateLimits['submitpayment']['ip']
			return;
		}

		// Get the data
		$this->donationData = $this->extractRequestParams();

		// Create a contribution tracking id
		$generator = SequenceGenerators\Factory::getSequenceGenerator( 'contribution-tracking' );
		$this->contributionTrackingId = $generator->getNext();

		// Create orderId
		$this->orderId = $this->contributionTrackingId . '.1';
		$this->donationData['order_id'] = $this->orderId;

		// Set up logging
		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		$this->logger = DonationLoggerFactory::getLoggerForType(
			$className,
			$this->orderId . ':' . $this->orderId
		);

		// App is sending payment_method=applepay, eventual android will send googlepay
		// TODO what if it's not there
		if ( $this->donationData['payment_method'] == 'applepay' ) {
			$this->donationData['payment_method'] = 'apple';
			$this->donationData['utm_campaign'] = 'iOS';
		} else {
			$this->logger->error( 'Payment method of ' . $this->donationData['payment_method'] . ' not available' );
			$response['status'] = self::STATUS_ERROR;
			$response['error_message'] = $this->generateErrorMessage();
			$response['order_id'] = $this->orderId;
			$this->getResult()->addValue( null, 'response', $response );
			return;
		}

		// App is sending payment_network which is our payment_submethod, match what we do in the adyen form
		$this->donationData['payment_submethod'] = $this->mapNetworktoSubmethod( $this->donationData['payment_network'] );

		$debugparams = $this->donationData;
		unset( $debugparams['payment_token'] );
		$this->logger->info( ' Starting submitPayment request with: ' . json_encode( $debugparams ) );

		// Set up gateway
		DonationInterface::setSmashPigProvider( $this->gateway );

		// Get the account
		$this->gatewayAccount = array_shift( ( array_keys( $this->getConfig()->get( 'AdyenCheckoutGatewayAccountInfo' ) ) ) );

		$this->sendToContributionTracking();

		$paymentProvider = PaymentProviderFactory::getProviderForMethod( $this->donationData['payment_method'] );

		try {
			$createPaymentResponse = $paymentProvider->createPayment( $this->donationData );

			if ( !$createPaymentResponse->isSuccessful() ) {
				$this->returnError( $createPaymentResponse->getRawResponse() );
				return;
			}
			$response['status'] = self::STATUS_SUCCESS;
		} catch ( Exception $e ) {
			$this->returnError( $e );
			return;
		}

		$this->gatewayTransactionId = $createPaymentResponse->getGatewayTxnId();

		// Need to grab the token if it's recurring
		if ( $this->donationData['recurring'] ) {
			$this->donationData['recurring_payment_token'] = $createPaymentResponse->getRecurringPaymentToken();
			$this->donationData['processor_contact_id'] = $createPaymentResponse->getProcessorContactID();
		}

		// Approve (capture) if needed
		if ( $createPaymentResponse->getStatus() === FinalStatus::PENDING_POKE ) {
			try {
				$approvePaymentResponse = $paymentProvider->approvePayment( [
					'amount' => $this->donationData['amount'],
					'currency' => $this->donationData['currency'],
					'gateway_txn_id' => $this->gatewayTransactionId,
				] );

				if ( !$approvePaymentResponse->isSuccessful() ) {
					$this->returnError( $approvePaymentResponse->getRawResponse() );
					return;
				}

				$response['status'] = self::STATUS_SUCCESS;
				$this->sendToPaymentsInit( 'complete' );
			} catch ( Exception $e ) {
				$this->returnError( $e );
				return;
			}
		}

		$this->sendToDonations();

		// Build the response
		$response['gateway_transaction_id'] = $this->gatewayTransactionId;
		$response['order_id'] = $this->orderId;

		$this->getResult()->addValue( null, 'response', $response );
	}

	public function getAllowedParams() {
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'app_version' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'banner' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'city' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'country' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'currency' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'donor_country' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'email' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'first_name' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'full_name' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'language' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'last_name' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'recurring' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'payment_token' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'opt_in' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'pay_the_fee' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'payment_method' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'payment_network' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'postal_code' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'state_province' => [ ParamValidator::PARAM_TYPE => 'string' ],
			'street_address' => [ ParamValidator::PARAM_TYPE => 'string' ],
		];
	}

	protected function generateErrorMessage() {
		$text = $this->msg( 'donate_interface-error-msg-general' )->inLanguage( $this->donationData['language'] )->text();
		$reference = $this->msg( 'donate_interface-error-reference', $this->orderId )->inLanguage( $this->donationData['language'] )->text();
		// There was an error processing your request. Error reference: 1234.5
		return $text . ' ' . $reference;
	}

	/**
	 * This allows the api to be hit without being logged in
	 * @return false
	 */
	public function isReadMode() {
		return false;
	}

	/**
	 * This function also exists in the frontend JS in adyen.js
	 * @param string $network
	 * @return string
	 */
	protected function mapNetworktoSubmethod( $network ) {
		$network = strtolower( $network );
		switch ( $network ) {
			case 'amex':
			case 'discover':
			case 'jcb':
			case 'visa':
				return $network;
			case 'cartesbancaires':
				return 'cb';
			case 'electron':
				return 'visa-electron';
			case 'mastercard':
				return 'mc';
			default:
				return '';
		}
	}

	public function mustBePosted() {
		return true;
	}

	protected function sendToContributionTracking() {
		// build utm key, looks like pay the fee is still taken from that
		if ( $this->donationData['pay_the_fee'] === 1 ) {
			$this->donationData['utm_key'] = 'ptf_1';
		}

		// Temporary until this is fixed on the app side, but if there is no banner set, set it to appmenu
		// TODO: Remove when the work in T350919 is done
		if ( !isset( $this->donationData['banner'] ) ) {
			$this->donationData['banner'] = 'appmenu';
		}

		$message = [
			'amount' => $this->donationData['amount'],
			'banner' => $this->donationData['banner'],
			'country' => $this->donationData['country'],
			'currency' => $this->donationData['currency'],
			'form_amount' => $this->donationData['currency'] . ' ' . $this->donationData['amount'],
			'gateway' => $this->gateway,
			'id' => $this->contributionTrackingId,
			'is_recurring' => $this->donationData['recurring'],
			'language' => $this->donationData['language'],
			'payment_method' => $this->donationData['payment_method'],
			'payment_submethod' => $this->donationData['payment_submethod'],
			'ts' => wfTimestamp( TS_MW ),
			'utm_key' => $this->donationData['utm_key'],
			// the utm_source from the form has banner.landingpage.payment_method
			'utm_source' => $this->donationData['banner'] . '.' . 'inapp' . '.' . $this->donationData['payment_method'],
			'utm_medium' => 'WikipediaApp',
			'utm_campaign' => $this->donationData['utm_campaign']
		];

		QueueWrapper::push( 'contribution-tracking', $message );
	}

	protected function sendToDonations() {
		$message = [
			'contribution_tracking_id' => $this->contributionTrackingId,
			'date' => UtcDate::getUtcTimestamp(),
			'gateway' => $this->gateway,
			// Pulling from the config
			'gateway_account' => $this->gatewayAccount,
			'gateway_txn_id' => $this->gatewayTransactionId,
			'order_id' => $this->orderId,
			'user_ip' => WmfFramework::getIP(),
			// donationData that needs to be renamed
			'country' => $this->donationData['donor_country'],
			'gross' => $this->donationData['amount']
		];

		// Add in donationData
		$keysToCopy = [
			'city',
			'currency',
			'language',
			'email',
			'first_name',
			'full_name',
			'last_name',
			'payment_method',
			'payment_submethod',
			'processor_contact_id',
			'postal_code',
			'opt_in',
			'recurring',
			'recurring_payment_token',
			'street_address',
			'state_province',
			'utm_key',
			'utm_medium',
			'utm_source'
		];

		foreach ( $keysToCopy as $key ) {
			if ( isset( $this->donationData[$key] ) ) {
				$message[$key] = $this->donationData[$key];
			}
		}

		QueueWrapper::push( 'donations', $message );
	}

	protected function sendToPaymentsInit( $status ) {
		$message = [
			'amount' => $this->donationData['amount'],
			'contribution_tracking_id' => $this->contributionTrackingId,
			'country' => $this->donationData['country'],
			'currency' => $this->donationData['currency'],
			'date' => UtcDate::getUtcTimestamp(),
			'gateway' => $this->gateway,
			'gateway_txn_id' => $this->gatewayTransactionId ?? '',
			'order_id' => $this->orderId,
			'payment_method' => $this->donationData['payment_method'],
			'payment_submethod' => $this->donationData['payment_submethod'],
			'payments_final_status' => $status,
			'server' => gethostname(),
			'validation_action' => self::VALIDATION_ACTION
		];

		QueueWrapper::push( 'payments-init', $message );
	}

	protected function returnError( $error ) {
		$this->logger->error( $error );
		$response['status'] = self::STATUS_ERROR;
		$response['error_message'] = $this->generateErrorMessage();
		$response['order_id'] = $this->orderId;
		$this->sendToPaymentsInit( 'failed' );
		$this->getResult()->addValue( null, 'response', $response );
	}
}
