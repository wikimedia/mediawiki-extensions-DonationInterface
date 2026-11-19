<?php

use MediaWiki\MediaWikiServices;
use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use Wikimedia\ParamValidator\ParamValidator;

class RecurringConversionApi extends DonationApiBase {
	/**
	 * Checks to see if the Donor backup key (Donor_BKUP) is set for the gateway in the current Session.
	 * If it's set, the function restores the Donor backup and clears the Donor_BKUP key.
	 * If there's a backup but for a separate gateway, no restore happens and the backup is cleared.
	 * @return bool
	 */
	private function restoreDonorSessionFromBackup() {
		$donor = WmfFramework::getSessionValue( GatewayAdapter::DONOR_BKUP );
		$restored = false;
		try {
			$donationData = $this->extractRequestParams();
			if ( $donor !== null && $donor['gateway'] == $donationData['gateway'] ) {
				WmfFramework::setSessionValue( GatewayAdapter::DONOR, $donor );
				$restored = true;
			}
		} catch ( Exception ) {
			WmfFramework::setSessionValue( GatewayAdapter::DONOR_BKUP, null );
			return false;
		}
		WmfFramework::setSessionValue( GatewayAdapter::DONOR_BKUP, null );
		return $restored;
	}

	public function execute() {
		// Check the required params up front, so a malformed request gets the standard
		// API error before the session is touched
		$this->extractRequestParams();

		// The adapter has to be built from the restored donation, so the token can only be
		// checked after the restore. Keep the session as it was in case the token is bad.
		$sessionBefore = [
			GatewayAdapter::DONOR => WmfFramework::getSessionValue( GatewayAdapter::DONOR ),
			GatewayAdapter::DONOR_BKUP => WmfFramework::getSessionValue( GatewayAdapter::DONOR_BKUP ),
		];
		$restored = $this->restoreDonorSessionFromBackup();
		// Only set once the token is known to be good, so anything that fails or throws
		// before then leaves the session as it was
		$tokenAccepted = false;
		try {
			if ( !$this->setAdapterAndValidate() ) {
				// setAdapterAndValidate() has already added the errors to the result
				$tokenAccepted = $this->adapter && $this->adapter->checkTokens();
				return;
			}
			$tokenAccepted = true;
			$logger = DonationLoggerFactory::getLogger( $this->adapter );
			if ( !( $this->adapter instanceof RecurringConversion ) ) {
				$this->getResult()->addValue(
					null,
					'errors',
					[ 'general' => 'This gateway does not support converting one-time donations to recurring' ]
				);
				return;
			}
			if ( $restored && $this->claimOrderForConversion( $sessionBefore[GatewayAdapter::DONOR_BKUP] ) ) {
				$paymentResult = $this->adapter->doRecurringConversion();
			} elseif ( $restored ) {
				// Another request (e.g. a double click) has already converted or declined this donation
				$logger->info( 'Multiple requests, donation has already been converted or declined.' );
				return;
			} else {
				// Without a backup there's no completed donation to convert.
				$logger->info( 'No donation in backup to restore session from.' );
				$paymentResult = PaymentResult::newFailure( [
					new PaymentError(
						'internal-0001',
						'No completed donation in session to convert',
						LogLevel::INFO
					)
				] );
			}

			$outputResult = [
				'redirect' => $paymentResult->getRedirect()
			];
			$errors = $paymentResult->getErrors();

			if ( $errors ) {
				$outputResult['errors'] = $this->serializeErrors( $errors );
				$this->getResult()->setIndexedTagName( $outputResult['errors'], 'error' );
			}

			$this->getResult()->addValue( null, 'result', $outputResult );
		} catch ( Exception $e ) {
			// Log the details, but don't send internal error text to the browser
			$this->getLogger()->error( 'Recurring conversion failed: ' . $e->getMessage() );
			$this->getResult()->addValue( null, 'result', [
				'errors' => [ 'general' => [ WmfFramework::formatMessage( 'donate_interface-error-msg-general' ) ] ]
			] );
		} finally {
			if ( !$tokenAccepted ) {
				// Leave session so leave the donor's session remains the same for the valid token
				foreach ( $sessionBefore as $key => $value ) {
					WmfFramework::setSessionValue( $key, $value );
				}
			} else {
				$this->clearSession( $restored );
			}
		}
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'amount' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'declineMonthlyConvert' => [ ParamValidator::PARAM_TYPE => 'boolean', ParamValidator::PARAM_REQUIRED => false ],
			'frequency_unit' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => false ],
			'gateway' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
			'wmf_token' => [ ParamValidator::PARAM_TYPE => 'string', ParamValidator::PARAM_REQUIRED => true ],
		];
	}

	/**
	 * Claims the donation's order ID so that only one request can convert or decline it.
	 * The session can't do this, as MediaWiki doesn't lock it between concurrent requests,
	 * so two requests can both read the backup before either clears it. add() only
	 * succeeds for the first request to claim the key.
	 *
	 * @param array $backup The restored Donor backup
	 * @return bool true if this request may go ahead
	 */
	private function claimOrderForConversion( array $backup ): bool {
		$orderId = $backup['order_id'] ?? '';
		if ( $orderId === '' ) {
			return false;
		}
		$key = 'recurring_conversion-' . $orderId;
		// A day is long enough to outlive any backup the session could put back
		return MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance()
			->add( $key, true, 86400 );
	}

	private function clearSession( bool $restored ) {
		WmfFramework::setSessionValue( GatewayAdapter::DONOR_BKUP, null );
		if ( $restored ) {
			WmfFramework::setSessionValue( GatewayAdapter::DONOR, null );
		}
	}
}
