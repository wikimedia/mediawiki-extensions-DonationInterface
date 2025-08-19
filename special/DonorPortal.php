<?php

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

class DonorPortal extends UnlistedSpecialPage {

	// We have to override setClientVariables in the current class, so alias it.
	use RequestNewChecksumLinkTrait {
		RequestNewChecksumLinkTrait::setClientVariables as setChecksumClientVariables;
	}

	protected array $formParams = [];

	public function __construct() {
		parent::__construct( 'DonorPortal' );
	}

	/**
	 * Render the donor portal page, or a login page if no checksum is provided
	 * @param string|null $subPage
	 * @return void
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->setUpClientSideChecksumRequest( $subPage );
		$this->addStylesScriptsAndViewport();
		$this->getOutput()->setPageTitleMsg( $this->msg( 'donorportal-title' ) );

		if ( $this->isChecksumExpired() ) {
			$this->formParams = [ 'showLogin' => true ];
		} else {
			$this->assignFormParameters();
		}
	}

	/**
	 * Set variables to be read in client-side JS code
	 * @param array &$vars
	 * @return void
	 */
	public function setClientVariables( array &$vars ) {
		// Call the (renamed) function from RequestNewChecksumLinkTrait
		$this->setChecksumClientVariables( $vars );
		$vars['donorData'] = $this->formParams;
		$vars['help_email'] = $this->getConfig()->get( 'DonationInterfaceEmailFormHelpEmail' );
		$vars['requestDonorPortalPage'] = $this->getPageTitle()->getBaseText();
	}

	/**
	 * Fetches donor data from Civiproxy and assigns template paramates to
	 * $this->formParams
	 *
	 * @return void
	 */
	private function assignFormParameters(): void {
		$requestParameters = $this->getRequest()->getValues();
		$donorSummary = CiviproxyConnect::getDonorSummary(
			$requestParameters['checksum'],
			$requestParameters['contact_id']
		);
		$locale = $this->getLocale( $donorSummary );
		$this->formParams = $donorSummary;
		$this->addContributionsToFormParams( $donorSummary['contributions'] ?? [], $locale );
		$this->addRecurringContributionsToFormParams( $donorSummary['recurringContributions'] ?? [], $locale );

		$this->formParams['donorID'] = 'CNTCT-' . $donorSummary['id'];
		$config = $this->getConfig();
		$this->formParams['endowmentLearnMoreUrl'] = $config->get( 'DonationInterfaceEndowmentLearnMoreURL' );
		$this->formParams['endowmentDonationUrl'] = $config->get( 'DonationInterfaceEndowmentDonationURL' );
		$this->formParams = array_merge( $this->formParams, $requestParameters );
	}

	/**
	 * Get the locale to use in currency formatters
	 *
	 * @param array $donorSummary
	 * @return string
	 */
	private function getLocale( array $donorSummary ) {
		$uiLang = $this->getLanguage()->getCode();
		$locale = 'en_US'; // fallback language
		if ( !empty( $donorSummary['country'] ) && $uiLang ) {
			$locale = $uiLang . '_' . $donorSummary['country'];
		}
		return $locale;
	}

	/**
	 * Add information about past donations to $this->formParams
	 *
	 * @param array $contributions
	 * @param string $locale
	 * @return void
	 */
	private function addContributionsToFormParams( array $contributions, string $locale ): void {
		// sort donations into annual fund vs endowment
		$this->formParams['annualFundContributions'] = $this->formParams['endowmentContributions'] = [];
		$mostRecentDonationDate = '1970-01-01';
		foreach ( $contributions as $contribution ) {
			if ( empty( $contribution['amount'] ) || empty( $contribution['currency'] ) ) {
				continue;
			}
			$contribution['amount_formatted'] = EmailForm::amountFormatter(
				(float)$contribution['amount'], $locale, $contribution['currency']
			);
			$contribution['receive_date_formatted'] = EmailForm::dateFormatter(
				$contribution['receive_date']
			);
			if ( $contribution['receive_date'] > $mostRecentDonationDate ) {
				$mostRecentDonationDate = $contribution['receive_date'];
				$this->formParams['onetimeContribution'] = [
					'last_amount' => $contribution['amount'],
					'last_currency' => $contribution['currency'],
					'last_payment_method' => $contribution['payment_method'],
					'last_amount_formatted' => EmailForm::amountFormatter(
						(float)$contribution['amount'], $locale, $contribution['currency']
					),
					'last_receive_date_formatted' => EmailForm::dateFormatter(
						$contribution['receive_date']
					),
					'id' => $contribution['id'],
				];
			}

			$contribution['donation_type_key'] = match ( $contribution['frequency_unit'] ) {
				'year' => 'donorportal-donation-type-annual',
				'month' => 'donorportal-donation-type-monthly',
				default => 'donorportal-donation-type-one-time',
			};

			if ( $contribution['financial_type'] === 'Endowment Gift' ) {
				$this->formParams['endowmentContributions'][] = $contribution;
			} else {
				$this->formParams['annualFundContributions'][] = $contribution;
			}
			// TODO: localize payment methods
		}
	}

	/**
	 * Add information about recurring donations to $this->formParams
	 *
	 * @param array $recurringContributions
	 * @param string $locale
	 * @return void
	 */
	private function addRecurringContributionsToFormParams( array $recurringContributions, string $locale ) {
		$this->formParams['hasActiveRecurring'] = $this->formParams['hasInactiveRecurring'] = false;
		$this->formParams['recurringContributions'] = $this->formParams['inactiveRecurringContributions'] = [];

		foreach ( $recurringContributions as $recurringContribution ) {
			if ( in_array(
				$recurringContribution['status'], [ 'In Progress', 'Pending', 'Failing', 'Processing', 'Overdue' ]
			) ) {
				$this->formParams['hasActiveRecurring'] = true;
				$key = 'recurringContributions';
			} elseif ( in_array(
				$recurringContribution['status'], [ 'Completed', 'Failed', 'Cancelled' ]
			) ) {
				$this->formParams['hasInactiveRecurring'] = true;
				$key = 'inactiveRecurringContributions';
			} else {
				// unrecognized status, skip this one!
				continue;
			}

			$recurringContribution['amount_formatted'] = EmailForm::amountFormatter(
				(float)$recurringContribution['amount'], $locale, $recurringContribution['currency'] ?? ''
			);
			$recurringContribution['next_sched_contribution_date_formatted'] = EmailForm::dateFormatter(
				$recurringContribution['next_sched_contribution_date'] ?? ''
			);
			if ( isset( $recurringContribution['last_contribution_date'] ) ) {
				$recurringContribution['last_contribution_date_formatted'] = EmailForm::dateFormatter(
					$recurringContribution['last_contribution_date']
				);
				// Need this bool to skip showing last contribution line for recurrings that
				// were cancelled without any successful donations (e.g. monthly convert)
				$recurringContribution['hasLastContribution'] = true;
			}

			if ( $recurringContribution['frequency_unit'] === 'year' ) {
				$recurringContribution['amount_frequency_key'] = 'donorportal-recurring-amount-annual';
				$recurringContribution['restart_key'] = 'donorportal-restart-annual';
			} else {
				$recurringContribution['amount_frequency_key'] = 'donorportal-recurring-amount-monthly';
				$recurringContribution['restart_key'] = 'donorportal-restart-monthly';
			}

			$this->formParams[$key][] = $recurringContribution;
			// TODO: localize payment methods
		}
	}

	/**
	 * @return void
	 */
	public function addStylesScriptsAndViewport(): void {
		$out = $this->getOutput();

		$context = RequestContext::getMain();
		$templatePath = $context->getConfig()->get( 'ScriptPath' ) .
			'/extensions/DonationInterface/email_forms/templates';
		$assetsPath = $context->getConfig()->get( 'ScriptPath' ) .
			'extensions/DonationInterface/modules/ext.donationInterface.donorPortal/assets';

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.donorPortalStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.donorPortal'
		] );
		// Tell the errorLog module which action to call
		$out->addJsConfigVars( [
			// TODO: rename this action for reuse
			'ClientErrorLogAction' => 'logRecurUpgradeFormError',
			'template_path' => $templatePath,
			'assets_path' => $assetsPath
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
}
