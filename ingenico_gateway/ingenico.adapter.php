<?php

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\CrmLink\FinalStatus;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class IngenicoAdapter extends GlobalCollectAdapter {
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
	function setGatewayDefaults( $options = array() ) {
		if ( isset( $options['returnTo'] ) ) {
			$returnTo = $options['returnTo'];
		} else {
			$returnTo = Title::newFromText( 'Special:IngenicoGatewayResult' )->getFullURL( false, false, PROTO_CURRENT );
		}

		$defaults = array(
			'returnto' => $returnTo,
			'attempt_id' => '1',
			'effort_id' => '1',
		);

		$this->addRequestData( $defaults );
	}

	public function defineTransactions() {
		parent::defineTransactions();
		$this->transactions['createHostedCheckout'] = array(
			'request' => array(
				'cardPaymentMethodSpecificInput' => array(
					'skipAuthentication'
				),
				'hostedCheckoutSpecificInput' => array(
					'isRecurring',
					'locale',
					'returnCancelState',
					'paymentProductFilters' => array(
						'restrictTo' => array(
							'products' => array(
								// HACK! this array should be a simple
								// list of payment ids, not an associative array
								// so... use 'null' to flag that?
								'paymentProductId' => null
							)
						)
					),
					'returnUrl',
					'showResultPage',
					// 'tokens', // we don't store user accounts or tokens here
					// 'variant', // For a/b testing of iframe
				),
				'order' => array(
					'amountOfMoney' => array(
						'amount',
						'currencyCode',
					),
					'customer' => array(
						'billingAddress' => array(
							'city',
							'countryCode',
							// 'houseNumber' // hmm, hope this isn't used for fraud detection!
							'state',
							// 'stateCode', // should we use this instead?
							'street',
							'zip',
						),
						'contactDetails' => array(
							'emailAddress'
						),
						// 'fiscalNumber' // only required for boletos & Brazil paypal
						'locale', // used for redirection to 3rd parties
						'personalInformation' => array(
							'name' => array(
								'firstName',
								'surname',
							)
						)
					),
					/*'items' => array(
						array(
							'amountOfMoney' => array(
								'amount',
								'currencyCode',
							),
							'invoiceData' => array(
								'description'
							)
						)
					),*/
					'references' => array(
						'descriptor', // First 22+ chars appear on card statement
						'merchantReference', // unique, string(30)
					)
				)
			),
			'values' => array(
				'showResultPage' => 'false',
				'returnCancelState' => true,
				'descriptor' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			),
			'response' => array(
				'hostedCheckoutId'
			)
		);

		$this->transactions['getHostedPaymentStatus'] = array(
			'request' => array( 'hostedCheckoutId' ),
			'response' => array(
				'id',
				'amount',
				'currencyCode',
				'avsResult',
				'cvvResult',
				'statusCode',
			)
		);

		$this->transactions['getPaymentStatus'] = array(
			'request' => array( 'id' ),
			'response' => array(
				'amount',
				'currencyCode',
				'avsResult',
				'cvvResult',
				'statusCode',
			)
		);

		$this->transactions['approvePayment'] = array(
			'request' => array( 'id' ),
			'response' => array( 'statusCode' )
		);

		$this->transactions['cancelPayment'] = array(
			'request' => array( 'id' ),
			'response' => array( 'statusCode' )
		);
	}

	/**
	 * Sets up the $order_id_meta array.
	 * Should contain the following keys/values:
	 * 'alt_locations' => array( $dataset_name, $dataset_key ) //ordered
	 * 'type' => numeric, or alphanumeric
	 * 'length' => $max_charlen
	 */
	public function defineOrderIDMeta() {
		$this->order_id_meta = array(
			'alt_locations' => array(),
			'ct_id' => true,
			'generate' => true,
		);
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
				);
				break;
			case 'approvePayment':
				$id = $data['id'];
				unset( $data['id'] );
				$result = $provider->approvePayment( $id, $data );
				break;
			case 'cancelPayment':
				$id = $data['id'];
				unset( $data['id'] );
				$result = $provider->cancelPayment( $id );
				break;
			default:
				return false;
		}

		$this->transaction_response->setRawResponse( json_encode( $result ) );
		return true;
	}

	public function getBasedir() {
		return __DIR__;
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
		if ( $this->getData_Unstaged_Escaped( 'recurring' ) ) {
			$this->transactions['createHostedCheckout']['request']['cardPaymentMethodSpecificInput'] =
				array_merge(
					$this->transactions['createHostedCheckout']['request']['cardPaymentMethodSpecificInput'],
					array(
						'tokenize',
						'recurringPaymentSequenceIndicator'
					)
				);
			$this->transactions['createHostedCheckout']['values']['tokenize'] = true;
			$this->transactions['createHostedCheckout']['values']['isRecurring'] = true;
			$this->transactions['createHostedCheckout']['values']['recurringPaymentSequenceIndicator'] = 'first';
			$desc = WmfFramework::formatMessage( 'donate_interface-monthly-donation-description' );
			$this->transactions['createHostedCheckout']['values']['descriptor'] = $desc;
			if ( array_search( 'tokens', $this->transactions['getHostedPaymentStatus']['response'] ) === false ) {
				$this->transactions['getHostedPaymentStatus']['response'][] = 'tokens';
			}
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
		$errors = array();
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
		$flattened = array();
		$squashMe = function ( $sourceData, $squashMe ) use ( &$flattened ) {
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

	protected function setGatewayTransactionId() {
		// FIXME: See 'Silly' comment in PayPal Express adapter
		$this->transaction_response->setGatewayTransactionId(
			$this->getData_Unstaged_Escaped( 'gateway_txn_id' )
		);
	}

	protected function approvePayment() {
		return $this->do_transaction( 'approvePayment' );
	}

	protected function getStatusCode( $txnData ) {
		return $this->getData_Unstaged_Escaped( 'gateway_status' );
	}

	public function cancel() {
		return $this->do_transaction( 'cancelPayment' );
	}

	public function shouldRectifyOrphan() {
		return true;
	}
}
