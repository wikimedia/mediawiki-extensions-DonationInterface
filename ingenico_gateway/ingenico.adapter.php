<?php

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

	public function defineTransactions() {
		parent::defineTransactions();
		$this->transactions['createHostedCheckout'] = array(
			'request' => array(
				'hostedCheckoutSpecificInput' => array(
					'isRecurring',
					'locale',
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
				'returnUrl' => $returnTitle = Title::newFromText( 'Special:IngenicoGatewayResult' )
					->getFullURL( false, false, PROTO_CURRENT ),
				'showResultPage' => 'false',
				'descriptor' => WmfFramework::formatMessage( 'donate_interface-donation-description' ),
			),
			'response' => array(
				'hostedCheckoutId'
			)
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

	public function curl_transaction( $data ) {
		$email = $this->getData_Unstaged_Escaped( 'email' );
		$this->logger->info( "Making API call for donor $email" );

		$filterResult = $this->runSessionVelocityFilter();
		if ( $filterResult === false ) {
			return false;
		}

		$provider = $this->getPaymentProvider();
		switch ( $this->getCurrentTransaction() ) {
			case 'createHostedCheckout':
				$result = $provider->createHostedPayment( $data );
				$this->transaction_response->setRawResponse( json_encode( $result ) );
				return true;
			default:
				return false;
		}
	}

	public function getBasedir() {
		return __DIR__;
	}

	public function do_transaction( $transaction ) {
		// If this is not our first call, get a fresh order ID
		// FIXME: This is repeated in four places. Maybe always regenerate in incrementSequenceNumber?
		if ( $this->session_getData( 'sequence' ) ) {
			$this->regenerateOrderID();
		}
		if ( $transaction === 'createHostedCheckout' ) {
			$this->incrementSequenceNumber();
		}
		$result = parent::do_transaction( $transaction );
		// Add things to session which may have been retrieved from API
		$this->session_addDonorData();
		return $result;
	}

	protected function getPaymentProvider() {
		$method = $this->getData_Unstaged_Escaped( 'payment_method' );
		return PaymentProviderFactory::getProviderForMethod( $method );
	}

	public function parseResponseCommunicationStatus( $response ) {
		return true;
	}

	public function parseResponseErrors( $response ) {
		return array();
	}

	public function parseResponseData( $response ) {
		if ( isset( $response['partialRedirectUrl'] ) ) {
			$provider = $this->getPaymentProvider();
			$response['FORMACTION'] = $provider->getHostedPaymentUrl(
				$response['partialRedirectUrl']
			);
		}
		return $response;
	}
}
