<?php
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentData\ReferenceData\CurrencyRates;

class RecurUpgrade extends UnlistedSpecialPage {

	const FALLBACK_COUNTRY = 'US';
	const FALLBACK_LANGUAGE = 'en_US';
	const FALLBACK_SUBPAGE = 'recurUpgradeError';
	const FALLBACK_CURRENCY = 'USD';

	const DONOR_DATA = 'Donor';

	// Note: Coordinate with Getpreferences.php in Civiproxy API, in wmf-civicrm extension.
	const CIVI_NO_RESULTS_ERROR = 'No result found';

	const BAD_DATA_SESSION_KEY = 'recur-upgrade-bad_data';

	public function __construct() {
		parent::__construct( 'RecurUpgrade' );
	}

	public function execute( $subpage ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.emailPreferences',
			'ext.donationInterface.recurUpgrade'
		] );
		$this->setPageTitle( $subpage );
		$params = $this->getRequest()->getValues();
		$posted = $this->getRequest()->wasPosted();
		if ( !$this->validate( $params, $posted ) ) {
			$this->renderError( $subpage );
			return;
		}
		if ( $posted ) {
			if ( $subpage !== 'recurUpgrade' ) {
				$this->renderError();
				return;
			}
			$this->executeRecurUpgrade( $params );
		} else {
			if ( $subpage === 'recurUpgrade' ) {
				$formParams = $this->paramsForRecurUpgradeForm(
					$params[ 'checksum' ],
					$params[ 'contact_id' ],
					$params[ 'country' ] ?? null
				);
				if ( $formParams === null ) {
					$this->renderError();
					return;
				}
				$params += $formParams;
				if ( $this->wasCanceled( $params ) ) {
					$this->sendCancelRecurringUpgradeQueue( $formParams['contribution_recur_id'], $params[ 'contact_id' ] );
					$this->redirectToCancel( $formParams['country'] );
					return;
				}
			}
			// if subpage null, we must have no checksum and contact id, so just render a default page
			$this->renderForm( $subpage ?? self::FALLBACK_SUBPAGE, $params );
		}
	}

	protected function sendCancelRecurringUpgradeQueue( $contributionId, $contactId ) {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'RecurUpgrade', true, false, '', null );
		$message = [
			'txn_type' => 'recurring_upgrade_decline',
			'contribution_recur_id' => $contributionId,
			'contact_id' => $contactId,
		];
		try {
			$logger->info( "Pushing recurring_upgrade_decline to recurring-upgrade queue with contribution_recur_id: {$message['contribution_recur_id']}" );
			QueueWrapper::push( 'recurring-upgrade', $message );
		} catch ( Exception $e ) {
			$logger->error( "Error pushing recurring_upgrade_decline message: {$e->getMessage()}" );
		}
	}

	protected function paramsForRecurUpgradeForm( $checksum, $contact_id, $country ) {
		$recurData = CiviproxyConnect::getRecurDetails( $checksum, $contact_id );
		if ( $recurData[ 'is_error' ] ) {
			$logger = DonationLoggerFactory::getLoggerFromParams(
				'RecurUpgrade', true, false, '', null );

			// If Civi returned no match for hash and contact_id, we still show the form,
			// but log a message and set a session flag to prevent a message being
			// placed on the queue.
			if ( $recurData[ 'error_message' ] == self::CIVI_NO_RESULTS_ERROR ) {
				$logger->warning(
					"No results for contact_id $contact_id with checksum $checksum" );

				WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, true );

			} else {
				$logger->error( 'Error from civiproxy: ' . $recurData[ 'error_message' ] );
			}
			return null;
		} else {
			WmfFramework::setSessionValue( self::BAD_DATA_SESSION_KEY, false );
		}
		$nextDateFormatted = EmailForm::dateFormatter( $recurData['next_sched_contribution_date'] );

		$uiLang = $this->getLanguage()->getCode();
		$locale = self::FALLBACK_LANGUAGE;
		// If no country param on query string, use the country from the donor address.
		$country = $country ?? $recurData['country'];
		if ( $country && $uiLang ) {
			$locale = $uiLang . '_' . $country;
		}

		WmfFramework::setSessionValue( self::DONOR_DATA, [
			'contribution_recur_id' => $recurData['id'],
			'amount' => $recurData['amount'],
			'currency' => $recurData['currency'],
			'country' => $country,
			'next_sched_contribution_date' => $recurData['next_sched_contribution_date']
		] );

		$allRecurringOptions = $this->getConfig()->get( 'DonationInterfaceRecurringUpgradeOptions' );
		$currency = $recurData['currency'];
		$optionsForSelectedCurrency = $allRecurringOptions[$currency] ?? $allRecurringOptions[self::FALLBACK_CURRENCY];
		$optionsForTemplate = [];
		foreach ( $optionsForSelectedCurrency as $option ) {
			$optionsForTemplate[] = [
				'value' => $option,
				'value_formatted' => EmailForm::amountFormatter( $option, $locale, $currency )
			];
		}

		return [
			'full_name' => $recurData['donor_name'],
			'recur_amount' => $recurData['amount'],
			'recur_amount_formatted' => EmailForm::amountFormatter( $recurData['amount'], $locale, $currency ),
			'contribution_recur_id' => $recurData['id'],
			'next_sched_date' => $recurData['next_sched_contribution_date'],
			'next_sched_date_formatted' => $nextDateFormatted,
			'country' => $country ?? self::FALLBACK_COUNTRY,
			'currency' => $currency,
			'maximum' => $this->getMaxInSelectedCurrency( $recurData ),
			'locale' => $locale,
			'recurringOptions' => $optionsForTemplate,
		];
	}

	protected function executeRecurUpgrade( $params ) {
		$logger = DonationLoggerFactory::getLoggerFromParams(
			'RecurUpgrade', true, false, '', null );
		$DonorData = WmfFramework::getSessionValue( self::DONOR_DATA );
		if ( !isset( $DonorData['contribution_recur_id'] ) ) {
			$this->renderError();
			return;
		}
		if ( $this->wasCanceled( $params ) ) {
			$this->sendCancelRecurringUpgradeQueue( $DonorData['contribution_recur_id'], $params['contact_id'] );
			$this->redirectToCancel( $DonorData['country'] );
			return;
		}
		$upgradeAmount = ( $params['upgrade_amount'] === 'other' )
			? $params['upgrade_amount_other']
			: $params['upgrade_amount'];

		$amount = $DonorData['amount'] + round( (double)$upgradeAmount, 2 );
		$message = [
			'txn_type' => 'recurring_upgrade',
			'contribution_recur_id' => $DonorData['contribution_recur_id'],
			'amount' => $amount,
			'currency' => $DonorData['currency']
		];

		try {
			$logger->info( "Pushing upgraded amount to recurring-upgrade queue with contribution_recur_id: {$message['contribution_recur_id']}" );
			QueueWrapper::push( 'recurring-upgrade', $message );
			$this->redirectToSuccess( $DonorData, $amount );
		} catch ( Exception $e ) {
			$logger->error( "Error pushing upgraded amount to recurring-upgrade queue: {$e->getMessage()}" );
			$this->renderError();
		}
	}

	protected function redirectToCancel( ?string $country ): void {
		$this->redirectToThankYouPage( [
			'country' => $country,
			'recurUpgrade' => 0,
		] );
	}

	protected function renderError( $subpage = 'recurUpgrade' ) {
		$subpage .= 'Error';
		$this->renderForm( $subpage, [] );
	}

	protected function redirectToSuccess( array $donorData, float $amount ): void {
		$redirectParams = [
			'country' => $donorData['country'],
			'recurAmount' => $amount,
			'recurCurrency' => $donorData['currency'],
			'recurDate' => $donorData['next_sched_contribution_date'],
			'recurUpgrade' => 1,
		];
		$this->redirectToThankYouPage( $redirectParams );
	}

	protected function redirectToThankYouPage( array $params ): void {
		$page = $this->getConfig()->get( 'DonationInterfaceThankYouPage' );
		$page = ResultPages::appendLanguageAndMakeURL(
			$page,
			$this->getLanguage()->getCode()
		);

		$this->getOutput()->redirect( wfAppendQuery( $page, $params ) );
	}

	protected function renderForm( $subpage, array $params ) {
		if ( !$subpage ) {
			$this->renderError();
			return;
		}
		$formObj = new EmailForm( $subpage, $params );
		$this->getOutput()->addHTML( $formObj->getForm() );
	}

	protected function validate( array $params, $posted ) {
		if ( !$this->validateToken( $params, $posted ) ) {
			return false;
		}
		if ( !$this->validateAmount( $params, $posted ) ) {
			return false;
		}
		foreach ( $params as $name => $value ) {
			if ( in_array( $name, [ 'token', 'title', 'upgrade_amount', 'upgrade_amount_other' ], true ) ) {
				continue;
			}
			// The rest of the parameters should just be alphanumeric, underscore, and hyphen
			if ( !preg_match( '/^[a-zA-Z0-9_-]*$/', $value ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateToken( array $params, $posted ) {
		if ( empty( $params['token'] ) ) {
			if ( $posted ) {
				return false;
			}
		} else {
			$session = RequestContext::getMain()->getRequest()->getSession();
			$token = $session->getToken();
			if ( !$token->match( $params['token'] ) ) {
				return false;
			}
		}
		return true;
	}

	protected function validateAmount( array $params, bool $posted ): bool {
		if ( !$posted || ( isset( $params['submit'] ) && $params['submit'] === 'cancel' ) ) {
			// Not doing anything with the parameters unless we're posted, so don't worry about them
			return true;
		}
		if (
			empty( $params['upgrade_amount'] ) ||
			( $params['upgrade_amount'] === 'other' && empty( $params['upgrade_amount_other'] ) )
		) {
			return false;
		}
		if ( $params['upgrade_amount'] === 'other' ) {
			return $this->isNumberInBounds( $params['upgrade_amount_other'] );
		}
		return $this->isNumberInBounds( $params['upgrade_amount'] );
	}

	protected function isNumberInBounds( string $amount ): bool {
		if ( !is_numeric( $amount ) ) {
			return false;
		}
		$amount = floatval( $amount );
		// If the currency is in the session, use that to determine max upgrade amount
		$donorData = RequestContext::getMain()->getRequest()->getSessionData( self::DONOR_DATA );
		$max = $this->getMaxInSelectedCurrency( $donorData );
		return ( $amount > 0 && $amount <= $max );
	}

	protected function getMaxInSelectedCurrency( ?array $donorData ): float {
		$rates = CurrencyRates::getCurrencyRates();
		if (
			$donorData !== null &&
			!empty( $donorData['currency'] ) &&
			array_key_exists( $donorData['currency'], $rates )
		) {
			$rate = $rates[$donorData['currency']];
		} else {
			$rate = 1;
		}
		return $rate * $this->getConfig()->get( 'DonationInterfaceRecurringUpgradeMaxUSD' );
	}

	protected function setPageTitle( $subpage ) {
		$title = $this->msg( 'donate_interface-error-msg-general' );

		if ( $subpage === 'recurUpgrade' ) {
			$title = $this->msg( 'recurupgrade-title' );
		}

		$this->getOutput()->setPageTitle( $title );
	}

	protected function wasCanceled( $params ) {
		return ( isset( $params['submit'] ) && ( $params['submit'] === 'cancel' ) );
	}
}
