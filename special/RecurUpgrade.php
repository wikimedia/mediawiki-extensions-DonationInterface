<?php

use MediaWiki\Extension\DonationInterface\RecurUpgrade\Validator;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use SmashPig\Core\DataStores\QueueWrapper;

class RecurUpgrade extends UnlistedSpecialPage {

	use RequestNewChecksumLinkTrait;

	const FALLBACK_COUNTRY = 'US';
	const FALLBACK_LANGUAGE = 'en_US';
	const FALLBACK_CURRENCY = 'USD';

	const DONOR_DATA = 'Donor';

	// Note: Coordinate with Getpreferences.php in Civiproxy API, in wmf-civicrm extension.
	const CIVI_NO_RESULTS_ERROR = 'No result found';

	protected Validator $validator;

	public function __construct() {
		parent::__construct( 'RecurUpgrade' );
		$this->validator = new Validator( $this->getRequest()->getSession(), $this->getConfig() );
	}

	/** @inheritDoc */
	public function execute( $subpage ) {
		$this->setHeaders();
		$this->setUpClientSideChecksumRequest( $subpage );
		$this->addStylesScriptsAndViewport();

		$params = $this->getRequest()->getValues();
		$posted = $this->getRequest()->wasPosted();
		if ( !$this->validator->validate( $params, $posted ) ) {
			$this->renderError( $params );
			return;
		}
		if ( $posted ) {
			$this->executeRecurUpgrade( $params );
		} else {
			if ( $this->isChecksumExpired() ) {
				$this->renderForm( 'recurUpgrade', $params );
				return;
			}
			$formParams = $this->paramsForRecurUpgradeForm(
				$params[ 'checksum' ],
				$params[ 'contact_id' ],
				$params[ 'country' ] ?? null
			);
			if ( $formParams === null ) {
				$this->renderError( $params );
				return;
			} elseif ( $formParams['is_error'] && $formParams[ 'error_message' ] === self::CIVI_NO_RESULTS_ERROR ) {
				$this->renderEmpty( $params );
				return;
			}
			$this->addDataToSession( $formParams );
			$params += $formParams;
			if ( $this->wasCanceled( $params ) ) {
				$this->sendCancelRecurringUpgradeQueue( $formParams['contribution_recur_id'], $params[ 'contact_id' ] );
				$this->redirectToCancel( $formParams['country'] );
			}
			$this->renderForm( 'recurUpgrade', $params );
		}
	}

	protected function sendCancelRecurringUpgradeQueue( string $contributionID, string $contactID ) {
		$logger = self::getLogger();
		$message = [
				'txn_type' => 'recurring_upgrade_decline',
				'contribution_recur_id' => $contributionID,
				'contact_id' => $contactID,
			] + $this->getTrackingParametersWithoutPrefix();
		try {
			$logger->info( "Pushing recurring_upgrade_decline to recurring-modify queue with contribution_recur_id: {$message['contribution_recur_id']}" );
			QueueWrapper::push( 'recurring-modify', $message );
		} catch ( Exception $e ) {
			$logger->error( "Error pushing recurring_upgrade_decline message: {$e->getMessage()}" );
		}
	}

	protected function paramsForRecurUpgradeForm( string $checksum, string $contactID, ?string $country ): ?array {
		$recurData = CiviproxyConnect::getRecurDetails( $checksum, $contactID );
		if ( $recurData[ 'is_error' ] ) {
			$logger = self::getLogger();

			if ( $recurData[ 'error_message' ] == self::CIVI_NO_RESULTS_ERROR ) {
				$logger->warning(
					"No results for contact_id $contactID with checksum $checksum" );
				return $recurData;
			} else {
				$logger->error( 'Error from civiproxy: ' . $recurData[ 'error_message' ] );
			}
			return null;
		}
		$nextDateFormatted = EmailForm::dateFormatter( $recurData['next_sched_contribution_date'] );

		$uiLang = $this->getLanguage()->getCode();
		$locale = self::FALLBACK_LANGUAGE;
		// If no country param on query string, use the country from the donor address.
		$country ??= $recurData['country'];
		if ( $country && $uiLang ) {
			$locale = $uiLang . '_' . $country;
		}

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
				'next_sched_contribution_date' => $recurData['next_sched_contribution_date'],
				'next_sched_contribution_date_formatted' => $nextDateFormatted,
				'country' => $country ?? self::FALLBACK_COUNTRY,
				'currency' => $currency,
				'maximum' => $this->validator->getMaxInSelectedCurrency( $recurData ),
				'locale' => $locale,
				'recurringOptions' => $optionsForTemplate,
			] + $this->getTrackingParametersForForm();
	}

	protected function executeRecurUpgrade( array $params ) {
		$logger = self::getLogger();
		$donorData = WmfFramework::getSessionValue( self::DONOR_DATA );
		if ( !isset( $donorData['contribution_recur_id'] ) ) {
			$this->renderError( $params );
			return;
		}
		if ( $this->wasCanceled( $params ) ) {
			$this->sendCancelRecurringUpgradeQueue( $donorData['contribution_recur_id'], $params['contact_id'] );
			$this->redirectToCancel( $donorData['country'] );
			return;
		}
		$upgradeAmount = ( $params['upgrade_amount'] === 'other' )
			? $params['upgrade_amount_other']
			: $params['upgrade_amount'];

		$amount = $donorData['amount'] + round( (double)$upgradeAmount, 2 );
		$message = [
				'txn_type' => 'recurring_upgrade',
				'contribution_recur_id' => $donorData['contribution_recur_id'],
				'amount' => $amount,
				'currency' => $donorData['currency'],
			] + $this->getTrackingParametersWithoutPrefix();

		try {
			$logger->info( "Pushing upgraded amount to recurring-modify queue with contribution_recur_id: {$message['contribution_recur_id']}" );
			QueueWrapper::push( 'recurring-modify', $message );
			$this->redirectToSuccess( $donorData, $amount );
		} catch ( Exception $e ) {
			$logger->error( "Error pushing upgraded amount to recurring-modify queue: {$e->getMessage()}" );
			$this->renderError( $params );
		}
	}

	protected function redirectToCancel( ?string $country ): void {
		$this->redirectToThankYouPage( [
			'country' => $country,
			'recurUpgrade' => 0,
		] );
	}

	protected function renderError( array $params ) {
		$this->renderForm( 'recurUpgradeError', $params );
	}

	protected function renderEmpty( array $params ) {
		$this->renderForm( 'recurUpgradeEmpty', $params );
	}

	protected function redirectToSuccess( array $donorData, float $amount ): void {
		$redirectParams = [
			'country' => $donorData['country'],
			'recurAmount' => $amount,
			'recurCurrency' => $donorData['currency'],
			'recurDate' => substr( $donorData['next_sched_contribution_date'], 0, 10 ),
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

		$this->getOutput()->redirect(
			wfAppendQuery( $page, $params + $this->getTrackingParametersWithPrefix() )
		);
	}

	protected function renderForm( string $templateName, array $params ) {
		$formObj = new EmailForm( $templateName, $params );
		$this->getOutput()->addHTML( $formObj->getForm() );
		$this->getConfig();
	}

	protected function wasCanceled( array $params ): bool {
		return ( isset( $params['submit'] ) && ( $params['submit'] === 'cancel' ) );
	}

	protected function addDataToSession( array $formParams ) {
		WmfFramework::setSessionValue( self::DONOR_DATA, [
			'contribution_recur_id' => $formParams['contribution_recur_id'],
			'amount' => $formParams['recur_amount'],
			'currency' => $formParams['currency'],
			'country' => $formParams['country'],
			'next_sched_contribution_date' => $formParams['next_sched_contribution_date'],
		] );
	}

	protected function getTrackingParametersWithPrefix(): array {
		return $this->getRequest()->getValues( 'wmf_campaign', 'wmf_medium', 'wmf_source' );
	}

	protected function getTrackingParametersWithoutPrefix(): array {
		$paramsWithPrefix = $this->getTrackingParametersWithPrefix();
		$paramsWithoutPrefix = [];
		foreach ( $paramsWithPrefix as $name => $value ) {
			$paramsWithoutPrefix[ substr( $name, 4 ) ] = $value;
		}
		return $paramsWithoutPrefix;
	}

	protected function getTrackingParametersForForm(): array {
		return $this->getTrackingParametersWithoutPrefix() + [
				'campaign' => '',
				'medium' => '',
				'source' => '',
			];
	}

	/**
	 * @return void
	 */
	public function addStylesScriptsAndViewport(): void {
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles',
		] );

		$out->addModules( [
			'ext.donationInterface.emailPreferences',
			'ext.donationInterface.recurUpgrade',
			'ext.donationInterface.errorLog',
			'ext.donationInterface.requestNewChecksumLink',
		] );

		// Tell the errorLog module which action to call
		$out->addJsConfigVars( [
			'ClientErrorLogAction' => 'logRecurUpgradeFormError',
		] );

		$out->addHeadItem(
			'viewport',
			Html::element(
				'meta', [
					'name' => 'viewport',
					'content' => 'width=device-width, initial-scale=1',
				]
			)
		);
	}

	public static function getLogger(): LoggerInterface {
		return DonationLoggerFactory::getLoggerFromParams(
			'RecurUpgrade', true, false, '', null );
	}
}
