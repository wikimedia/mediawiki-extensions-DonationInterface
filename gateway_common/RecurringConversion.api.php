<?php

use Wikimedia\ParamValidator\ParamValidator;

class RecurringConversionApi extends DonationApiBase {

	public function execute() {
		$isValid = $this->setAdapterAndValidate();
		if ( !$isValid ) {
			return;
		}
		if ( !$this->adapter instanceof RecurringConversion ) {
			$this->getResult()->addValue(
				null,
				'errors',
				[ 'general' => 'This gateway does not support converting one-time donations to recurring' ]
			);
			return;
		}
		$paymentResult = $this->adapter->doRecurringConversion();

		$outputResult = [
			'redirect' => $paymentResult->getRedirect()
		];
		$errors = $paymentResult->getErrors();

		if ( !empty( $errors ) ) {
			$outputResult['errors'] = $this->serializeErrors( $errors );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	public function getAllowedParams() {
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'gateway' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
		];
	}
}
