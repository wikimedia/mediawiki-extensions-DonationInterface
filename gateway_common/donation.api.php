<?php
use SmashPig\Core\Logging\Logger;

/**
 * Generic Donation API
 * This API should be able to accept donation submissions for any gateway or payment type
 * Call with api.php?action=donate
 */
class DonationApi extends ApiBase {
	public $donationData, $gateway;
	public function execute() {
		$this->donationData = $this->extractRequestParams();

		$this->gateway = $this->donationData['gateway'];

		$method = $this->donationData['payment_method'];
		// @todo FIXME: Unused local variable.
		$submethod = $this->donationData['payment_submethod'];

		DonationInterface::setSmashPigProvider( $this->gateway );
		$gatewayObj = $this->getGatewayObject();

		// FIXME: SmashPig should just use Monolog.
		Logger::getContext()->enterContext( $gatewayObj->getLogMessagePrefix() );

		if ( !$gatewayObj ) {
			return; // already failed with a dieUsage call
		}

		$validated_ok = $gatewayObj->validatedOK();
		if ( !$validated_ok ) {
			$errors = $gatewayObj->getErrorState()->getErrors();
			$outputResult['errors'] = $this->serializeErrors( $errors, $gatewayObj );
			// FIXME: What is this junk?  Smaller API, like getResult()->addErrors
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
			$this->getResult()->addValue( null, 'result', $outputResult );
			return;
		}

		if ( $this->gateway == 'globalcollect' ) {
			switch ( $method ) {
				// TODO: add other iframe payment methods
				case 'cc':
					$result = $gatewayObj->do_transaction( 'INSERT_ORDERWITHPAYMENT' );
					break;
				default:
					$result = $gatewayObj->do_transaction( 'TEST_CONNECTION' );
			}
		} elseif ( $this->gateway == 'adyen' ) {
			$result = $gatewayObj->do_transaction( 'donate' );
		} elseif ( $this->gateway === 'paypal_ec' ) {
			$gatewayObj->doPayment();
			$result = $gatewayObj->getTransactionResponse();
		}

		// $normalizedData = $gatewayObj->getData_Unstaged_Escaped();
		$outputResult = array();
		if ( $result->getMessage() !== null ) {
			$outputResult['message'] = $result->getMessage();
		}
		if ( $result->getCommunicationStatus() !== null ) {
			$outputResult['status'] = $result->getCommunicationStatus();
		}

		$errors = $result->getErrors();
		$data = $result->getData();
		if ( !empty( $data ) ) {
			if ( array_key_exists( 'PAYMENT', $data )
				&& array_key_exists( 'RETURNURL', $data['PAYMENT'] )
			) {
				$outputResult['returnurl'] = $data['PAYMENT']['RETURNURL'];
			}
			if ( array_key_exists( 'FORMACTION', $data ) ) {
				$outputResult['formaction'] = $data['FORMACTION'];
				if ( empty( $errors ) ) {
					$gatewayObj->logPending();
				}
			}
			if ( array_key_exists( 'gateway_params', $data ) ) {
				$outputResult['gateway_params'] = $data['gateway_params'];
			}
			if ( array_key_exists( 'RESPMSG', $data ) ) {
				$outputResult['responsemsg'] = $data['RESPMSG'];
			}
			if ( array_key_exists( 'ORDERID', $data ) ) {
				$outputResult['orderid'] = $data['ORDERID'];
			}
		}
		if ( !empty( $errors ) ) {
			$outputResult['errors'] = $this->serializeErrors( $errors, $gatewayObj );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		if ( $this->donationData ) {
			$this->getResult()->addValue( null, 'request', $this->donationData );
		}
		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	protected function serializeErrors( $errors, GatewayAdapter $adapter ) {
		$serializedErrors = array();
		foreach( $errors as $error ) {
			if ( $error instanceof ValidationError ) {
				$message = WmfFramework::formatMessage(
					$error->getMessageKey(),
					$error->getMessageParams()
				);
				$serializedErrors[$error->getField()] = $message;
			} elseif ( $error instanceof PaymentError ) {
				$message = $adapter->getErrorMapByCodeAndTranslate( $error->getErrorCode() );
				$serializedErrors['general'][] = $message;
			} else {
				$logger = DonationLoggerFactory::getLogger( $adapter );
				$logger->error( 'API trying to serialize unknown error type: ' . get_class( $error ) );
			}
		}
		return $serializedErrors;
	}

	public function isReadMode() {
		return false;
	}

	public function getAllowedParams() {
		return array(
			'gateway' => $this->defineParam( true ),
			'amount' => $this->defineParam( false ),
			'currency' => $this->defineParam( false ),
			'first_name' => $this->defineParam( false ),
			'last_name' => $this->defineParam( false ),
			'street_address' => $this->defineParam( false ),
			'supplemental_address_1' => $this->defineParam( false ),
			'city' => $this->defineParam( false ),
			'state_province' => $this->defineParam( false ),
			'postal_code' => $this->defineParam( false ),
			'email' => $this->defineParam( false ),
			'country' => $this->defineParam( false ),
			'card_num' => $this->defineParam( false ),
			'card_type' => $this->defineParam( false ),
			'expiration' => $this->defineParam( false ),
			'cvv' => $this->defineParam( false ),
			'payment_method' => $this->defineParam( false ),
			'payment_submethod' => $this->defineParam( false ),
			'language' => $this->defineParam( false ),
			'order_id' => $this->defineParam( false ),
			'wmf_token' => $this->defineParam( false ),
			'utm_source' => $this->defineParam( false ),
			'utm_campaign' => $this->defineParam( false ),
			'utm_medium' => $this->defineParam( false ),
			'referrer' => $this->defineParam( false ),
			'recurring' => $this->defineParam( false ),
		);
	}

	private function defineParam( $required = false, $type = 'string' ) {
		if ( $required ) {
			$param = array( ApiBase::PARAM_TYPE => $type, ApiBase::PARAM_REQUIRED => true );
		} else {
			$param = array( ApiBase::PARAM_TYPE => $type );
		}
		return $param;
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=donate&gateway=globalcollect&amount=2.00&currency=USD'
				=> 'apihelp-donate-example-1',
		);
	}

	/**
	 * @return GatewayAdapter
	 */
	protected function getGatewayObject() {
		$className = DonationInterface::getAdapterClassForGateway( $this->gateway );
		return new $className();
	}
}
