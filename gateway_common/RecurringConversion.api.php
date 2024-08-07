<?php

use Wikimedia\ParamValidator\ParamValidator;

class RecurringConversionApi extends DonationApiBase {
	/**
	 * Checks to see if the Donor backup key (Donor_BKUP) is set for the gateway in the current Session.
	 * If it's set, the function restores the Donor backup and clears the Donor_BKUP key.
	 * If there's a backup but for a separate gateway, no restore happens and the backup is cleared.
	 */
	private function restoreDonorSessionFromBackup() {
		$donor = WmfFramework::getSessionValue( GatewayAdapter::DONOR_BKUP );
		try {
			$donationData = $this->extractRequestParams();
			if ( $donor !== null && $donor['gateway'] == $donationData['gateway'] ) {
				WmfFramework::setSessionValue( GatewayAdapter::DONOR, $donor );
			}
		} catch ( Exception $ex ) {
			WmfFramework::setSessionValue( GatewayAdapter::DONOR_BKUP, null );
			return;
		}
		WmfFramework::setSessionValue( GatewayAdapter::DONOR_BKUP, null );
	}

	public function execute() {
		$this->restoreDonorSessionFromBackup();
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

		if ( $errors ) {
			$outputResult['errors'] = $this->serializeErrors( $errors );
			$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
		}

		$this->getResult()->addValue( null, 'result', $outputResult );
	}

	public function getAllowedParams() {
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'declineMonthlyConvert' => [ ParamValidator::PARAM_TYPE => 'boolean', ParamValidator::PARAM_REQUIRED => false ],
			'frequency_unit' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'gateway' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
		];
	}
}
