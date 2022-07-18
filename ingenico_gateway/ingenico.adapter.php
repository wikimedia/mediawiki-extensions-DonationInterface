<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

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
		if ( isset( $options['returnTo'] ) ) {
			$returnTo = $options['returnTo'];
		} else {
			$returnTo = Title::newFromText( 'Special:IngenicoGatewayResult' )->getFullURL( false, false, PROTO_CURRENT );
		}

		$defaults = [
			'returnto' => $returnTo,
			'attempt_id' => '1',
			'effort_id' => '1',
			'processor_form' => 'default',
		];

		$this->addRequestData( $defaults );
	}

	public function defineTransactions() {
		parent::defineTransactions();
		$this->transactions['createHostedCheckout'] = [
			'request' => [
				'cardPaymentMethodSpecificInput' => [
					'skipAuthentication'
				],
				'hostedCheckoutSpecificInput' => [
					'isRecurring',
					'locale',
					'returnCancelState',
					'paymentProductFilters' => [
						'restrictTo' => [
							'groups',
						]
					],
					'returnUrl',
					'showResultPage',
					// 'tokens', // we don't store user accounts or tokens here
					'variant', // For a/b testing of iframe
				],
				'fraudFields' => [
					'customerIpAddress',
				],
				'order' => [
					'amountOfMoney' => [
						'amount',
						'currencyCode',
					],
					'customer' => [
						'billingAddress' => [
							'city',
							'countryCode',
							// 'houseNumber' // hmm, hope this isn't used for fraud detection!
							'state',
							// 'stateCode', // should we use this instead?
							'street',
							'zip',
						],
						'contactDetails' => [
							'emailAddress'
						],
						// 'fiscalNumber' // only required for boletos & Brazil paypal
						'locale', // used for redirection to 3rd parties
						'personalInformation' => [
							'name' => [
								'firstName',
								'surname',
							]
						]
					],
					/*'items' => [
						[
							'amountOfMoney' => [
								'amount',
								'currencyCode',
							],
							'invoiceData' => [
								'description'
							]
						]
					],*/
					'references' => [
						'descriptor', // First 22+ chars appear on card statement
						'merchantReference', // unique, string(30)
					]
				]
			],
			'values' => [
				'showResultPage' => 'false',
				'returnCancelState' => 'true',
				'descriptor' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
				'groups' => [
					'cards',
				]
			],
			'response' => [
				'hostedCheckoutId'
			]
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
		$apiResult = $this->do_transaction( 'createHostedCheckout' );
		$data = $apiResult->getData();
		// FIXME: stop using this legacy key here and in parseResponseData
		if ( !empty( $data['FORMACTION'] ) ) {
			if ( $this->getData_Staged( 'use_authentication' ) ) {
				// We're using 3D Secure, so we should redirect
				return PaymentResult::newRedirect( $data['FORMACTION'] );
			}
			return PaymentResult::newIframe( $data['FORMACTION'] );
		}
		return PaymentResult::newFailure( $apiResult->getErrors() );
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
			case 'createHostedCheckout':
				$result = $provider->createHostedPayment( $data );
				break;
			case 'getHostedPaymentStatus':
				$result = $provider->getHostedPaymentStatus(
					$data['hostedCheckoutId']
				)->getRawResponse();
				break;
			case 'approvePayment':
				$data['gateway_txn_id'] = $data['id'];
				unset( $data['id'] );
				/** @var \SmashPig\PaymentProviders\ApprovePaymentResponse $approvePaymentResponse */
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
		$this->tuneForRecurring();
		if ( $transaction === 'createHostedCheckout' ) {
			$this->ensureUniqueOrderID();
			$this->incrementSequenceNumber();
		}
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
		$cardSpecificInput = $this->transactions['createHostedCheckout']['request']['cardPaymentMethodSpecificInput'];
		$getStatusResponse = $this->transactions['getHostedPaymentStatus']['response'];
		if ( $this->showMonthlyConvert() ) {
			if ( array_search( 'tokenize', $cardSpecificInput ) === false ) {
				$this->transactions['createHostedCheckout']['request']['cardPaymentMethodSpecificInput'][] = 'tokenize';
			}
			$this->transactions['createHostedCheckout']['values']['tokenize'] = true;
			if ( array_search( 'tokens', $getStatusResponse ) === false ) {
				$this->transactions['getHostedPaymentStatus']['response'][] = 'tokens';
			}
		} elseif ( $isRecurring ) {
			$this->transactions['createHostedCheckout']['request']['cardPaymentMethodSpecificInput'] =
				array_merge(
					$cardSpecificInput,
					[
						'tokenize',
						'recurringPaymentSequenceIndicator'
					]
				);
			$this->transactions['createHostedCheckout']['values']['tokenize'] = true;
			$this->transactions['createHostedCheckout']['values']['isRecurring'] = true;
			$this->transactions['createHostedCheckout']['values']['recurringPaymentSequenceIndicator'] = 'first';
			if ( array_search( 'tokens', $getStatusResponse ) === false ) {
				$this->transactions['getHostedPaymentStatus']['response'][] = 'tokens';
			}
			$desc = WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' );
			$this->transactions['createHostedCheckout']['values']['descriptor'] = $desc;
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
