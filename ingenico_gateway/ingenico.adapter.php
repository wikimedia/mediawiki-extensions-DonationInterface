<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentData\ValidationAction;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;

class IngenicoAdapter extends GlobalCollectAdapter implements RecurringConversion {
	use RecurringConversionTrait;

	const GATEWAY_NAME = 'Ingenico';
	const IDENTIFIER = 'ingenico';
	const GLOBAL_PREFIX = 'wgIngenicoGateway';

	public function getCommunicationType() {
		return 'array';
	}

	public function getResponseType() {
		return 'json';
	}

	/**
	 * Setting some Ingenico-specific defaults.
	 * @param array $options These get extracted in the parent.
	 */
	protected function setGatewayDefaults( $options = [] ) {
		$returnTo = $options['returnTo'] ??
			Title::newFromText( 'Special:IngenicoGatewayResult' )->getFullURL( false, false, PROTO_CURRENT );

		$defaults = [
			'return_url' => $returnTo,
			'attempt_id' => '1',
			'effort_id' => '1',
			'processor_form' => 'default',
		];

		$this->addRequestData( $defaults );
	}

	public function defineTransactions() {
		parent::defineTransactions();
		$this->transactions['createPaymentSession'] = [
			'request' => [
				'use_3d_secure',
				'amount',
				'currency',
				'recurring',
				'return_url',
				'processor_form',
				'city',
				'street_address',
				'state_province',
				'postal_code',
				'email',
				'order_id',
				'description',
				'user_ip',
				'country',
				'language',
				'payment_submethod',
			],
			'values' => [
				'description' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			],
		];

		$this->transactions['getHostedPaymentStatus'] = [
			'request' => [ 'hostedCheckoutId' ],
			'response' => [
				'id',
				'amount',
				'currencyCode',
				'avsResult',
				'cvvResult',
				'statusCode',
				'paymentProductId',
				'cardholderName',
			]
		];

		$this->transactions['getPaymentStatus'] = [
			'request' => [ 'id' ],
			'response' => [
				'amount',
				'currencyCode',
				'avsResult',
				'cvvResult',
				'statusCode',
				'paymentProductId',
			]
		];

		$this->transactions['approvePayment'] = [
			'request' => [ 'id' ],
			'response' => [ 'statusCode' ]
		];

		$this->transactions['cancelPayment'] = [
			'request' => [ 'id' ],
			'response' => [ 'statusCode' ]
		];
	}

	/**
	 * Sets up the $order_id_meta array.
	 * Should contain the following keys/values:
	 * 'alt_locations' => [ $dataset_name, $dataset_key ] //ordered
	 * 'type' => numeric, or alphanumeric
	 * 'length' => $max_charlen
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = [
			'alt_locations' => [],
			'ct_id' => true,
			'generate' => true,
		];
	}

	public function doPayment() {
		$this->ensureUniqueOrderID();
		$this->incrementSequenceNumber();
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
		/** @var HostedCheckoutProvider $provider */
		$provider = $this->getPaymentProvider();
		$email = $this->getData_Unstaged_Escaped( 'email' );
		$this->logger->info( "Calling createPaymentSession for donor $email" );
		$createSessionResponse = $this->createPaymentSession( $provider );
		if ( $createSessionResponse->getRedirectUrl() ) {
			$this->addResponseData( [
				'gateway_session_id' => $createSessionResponse->getPaymentSession()
			] );
			$this->session_addDonorData();
			$this->logPaymentDetails();
			return PaymentResult::newIframe( $createSessionResponse->getRedirectUrl() );
		}
		return PaymentResult::newFailure( $createSessionResponse->getErrors() );
	}

	protected function createPaymentSession( HostedCheckoutProvider $provider ): CreatePaymentSessionResponse {
		$this->setCurrentTransaction( 'createPaymentSession' );
		$data = $this->buildRequestArray();
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			$data['description'] = WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' );
		}
		// FIXME: need special handling to pass through 'false' because it's erased by buildRequestArray
		if ( $this->getData_Staged( 'use_3d_secure' ) === false ) {
			$data['use_3d_secure'] = false;
		}
		// If we are going to ask for a monthly donation after a one-time donation completes, set the
		// recurring param to 1 to tokenize the payment.
		if ( $this->showMonthlyConvert() ) {
			$data['recurring'] = 1;
			// Since we're not sure if we're going to ever use the token, flag the transaction as
			// 'card on file' rather than 'subscription' (the default for recurring). This may avoid
			// donor complaints of one-time donations appearing as recurring on their card statement.
			$data['recurring_model'] = RecurringModel::CARD_ON_FILE;
		}
		return $provider->createPaymentSession( $data );
	}

	/**
	 * Make an API call to Ingenico Connect.
	 *
	 * @param array $data parameters for the transaction
	 * @return bool whether the API call succeeded
	 */
	public function curl_transaction( $data ) {
		$email = $this->getData_Unstaged_Escaped( 'email' );
		$this->logger->info( "Making API call for donor $email" );

		$filterResult = $this->runSessionVelocityFilter();
		if ( $filterResult === false ) {
			return false;
		}

		/** @var HostedCheckoutProvider $provider */
		$provider = $this->getPaymentProvider();
		switch ( $this->getCurrentTransaction() ) {
			case 'getHostedPaymentStatus':
				$paymentDetailResponse = $provider->getHostedPaymentStatus(
					$data['hostedCheckoutId']
				);
				$this->addResponseData( [
					'initial_scheme_transaction_id' => $paymentDetailResponse->getInitialSchemeTransactionId()
				] );
				$result = $paymentDetailResponse->getRawResponse();
				break;
			case 'approvePayment':
				$data['gateway_txn_id'] = $data['id'];
				unset( $data['id'] );
				/** @var \SmashPig\PaymentProviders\Responses\ApprovePaymentResponse $approvePaymentResponse */
				$approvePaymentResponse = $provider->approvePayment( $data );
				$result = $approvePaymentResponse->getRawResponse();
				break;
			case 'cancelPayment':
				// NOTE: this is currently not hit - there are two conditions that
				// lead to this code path but neither of them happen in practice.
				$id = $data['id'];
				unset( $data['id'] );
				$cancelPaymentResponse = $provider->cancelPayment( $id );
				$result = $cancelPaymentResponse->getRawResponse();
				break;
			default:
				return false;
		}

		$this->transaction_response->setRawResponse( json_encode( $result ) );
		return true;
	}

	public function do_transaction( $transaction ) {
		// Reset var_map and transformers to old-style
		$this->var_map = $this->config['legacy_var_map'];
		$this->config['transformers'] = $this->config['legacy_transformers'];
		$this->defineDataTransformers();
		$this->tuneForRecurring();
		$result = parent::do_transaction( $transaction );
		// Add things to session which may have been retrieved from API
		if ( !$this->getFinalStatus() ) {
			$this->session_addDonorData();
		}

		return $result;
	}

	/**
	 * Stage: recurring
	 * Adds the recurring payment pieces to the structure of createHostedCheckout
	 * and getHostedPaymentStatus if the recurring field is populated.
	 */
	protected function tuneForRecurring() {
		$isRecurring = $this->getData_Unstaged_Escaped( 'recurring' );
		$getStatusResponse = $this->transactions['getHostedPaymentStatus']['response'];
		if ( $this->showMonthlyConvert() ) {
			$this->transactions['createHostedCheckout']['values']['tokenize'] = true;
			if ( !in_array( 'tokens', $getStatusResponse ) ) {
				$this->transactions['getHostedPaymentStatus']['response'][] = 'tokens';
			}
		} elseif ( $isRecurring ) {
			if ( !in_array( 'tokens', $getStatusResponse ) ) {
				$this->transactions['getHostedPaymentStatus']['response'][] = 'tokens';
			}
			$desc = WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' );
			$this->transactions['createPaymentSession']['values']['description'] = $desc;
		}
	}

	protected function getPaymentProvider() {
		$method = $this->getData_Unstaged_Escaped( 'payment_method' );
		return PaymentProviderFactory::getProviderForMethod( $method );
	}

	public function parseResponseCommunicationStatus( $response ) {
		return true;
	}

	public function parseResponseErrors( $response ) {
		$errors = [];
		if ( !empty( $response['errors'] ) ) {
			foreach ( $response['errors'] as $error ) {
				$errors[] = new PaymentError(
					$error['code'],
					$error['message'],
					LogLevel::ERROR
				);
			}
		}
		return $errors;
	}

	public function parseResponseData( $response ) {
		// Flatten the whole darn nested thing.
		// FIXME: This should probably happen in the SmashPig library where
		// we can flatten in a custom way per transaction type. Or we should
		// expand var_map to work with nested stuff.
		$flattened = [];
		$squashMe = static function ( $sourceData, $squashMe ) use ( &$flattened ) {
			foreach ( $sourceData as $key => $value ) {
				if ( is_array( $value ) ) {
					call_user_func( $squashMe, $value, $squashMe );
				} else {
					// Hmm, we might be clobbering something
					$flattened[$key] = $value;
				}
			}
		};
		$squashMe( $response, $squashMe );
		if ( isset( $flattened['partialRedirectUrl'] ) ) {
			$provider = $this->getPaymentProvider();
			$flattened['FORMACTION'] = $provider->getHostedPaymentUrl(
				$flattened['partialRedirectUrl']
			);
			// Ingenico tells us we're sometimes sending users to the bare
			// checkout URL (55-ish chars) instead of the one with the checkout
			// ID on it (165 chars)
			if ( strlen( $flattened['FORMACTION'] ) < 100 ) {
				$message = 'FORMACTION suspiciously short! response was: ' .
					print_r( $response, true );
				$this->logger->error( $message );
			}

		}
		return $flattened;
	}

	public function processDonorReturn( $requestValues ) {
		// FIXME: make sure we're processing the order ID we expect!

		$response = $this->do_transaction( 'Confirm_CreditCard' );

		return PaymentResult::fromResults(
			$response,
			$this->getFinalStatus()
		);
	}

	protected function getOrderStatusFromProcessor() {
		// FIXME: sometimes we should use getPayment
		return $this->do_transaction( 'getHostedPaymentStatus' );
	}

	protected function post_process_getHostedPaymentStatus() {
		return parent::post_process_get_orderstatus();
	}

	protected function getGatewayTransactionId() {
		return $this->getData_Unstaged_Escaped( 'gateway_txn_id' );
	}

	protected function approvePayment() {
		return $this->do_transaction( 'approvePayment' );
	}

	/**
	 * Get gateway status code from unstaged data.
	 *
	 * Note: We currently add in substitute status codes for
	 * IN_PROGRESS and CANCELLED_BY_CONSUMER so that we can map these
	 * to a valid \SmashPig\PaymentData\FinalStatus. Ingenico does not return a
	 * status code for these two states, only the text description.
	 * This behaviour should updated when globalcollect is retired.
	 *
	 * @param array $txnData
	 *
	 * @return int|null
	 * @see \SmashPig\PaymentData\FinalStatus
	 */
	protected function getStatusCode( $txnData ) {
		$statusCode = $this->getData_Unstaged_Escaped( 'gateway_status' );
		if ( $statusCode == null &&
			in_array( $txnData['status'], [ 'IN_PROGRESS', 'CANCELLED_BY_CONSUMER' ] ) ) {
			switch ( $txnData['status'] ) {
				case 'CANCELLED_BY_CONSUMER':
					// maps to CANCELLED
					$statusCode = 99999;
					break;
				case 'IN_PROGRESS':
					// maps to the PENDING range
					$statusCode = 25;
					break;
			}
		}
		return $statusCode;
	}

	public function cancel() {
		return $this->do_transaction( 'cancelPayment' );
	}

	public function shouldRectifyOrphan() {
		return true;
	}

	public function getRequestProcessId( $requestValues ) {
		return $requestValues['hostedCheckoutId'];
	}

	public function getPaymentMethodsSupportingRecurringConversion(): array {
		return [ 'cc' ];
	}
}
