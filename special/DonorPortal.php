<?php

use MediaWiki\SpecialPage\UnlistedSpecialPage;

class DonorPortal extends UnlistedSpecialPage {

	use RequestNewChecksumLinkTrait;

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
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.emailPreferences',
			'ext.donationInterface.donorPortal',
		] );

		$context = RequestContext::getMain();
		$templatePath = $context->getConfig()->get( 'ScriptPath' ) .
			'/extensions/DonationInterface/email_forms/templates';

		$out->addJsConfigVars( [
			'template_path' => $templatePath,
		] );

		$this->getOutput()->setPageTitle( $this->msg( 'donorportal-title' ) );

		if ( $this->isChecksumExpired() ) {
			$this->formParams = [ 'showLogin' => true ];
		} else {
			$this->assignFormParameters();
		}
		// $formObj = new DonorPortalForm( 'donorPortal', $this->formParams );
		// $this->getOutput()->addHTML( $formObj->getForm() );
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
			$contribution['amount_formatted'] = EmailForm::amountFormatter(
				(float)$contribution['amount'], $locale, $contribution['currency']
			);
			$contribution['receive_date_formatted'] = EmailForm::dateFormatter(
				$contribution['receive_date']
			);
			if ( $contribution['receive_date'] > $mostRecentDonationDate ) {
				$mostRecentDonationDate = $contribution['receive_date'];
				$this->formParams['last_amount'] = $contribution['amount'];
				$this->formParams['last_currency'] = $contribution['currency'];
				$this->formParams['last_payment_method'] = $contribution['payment_method'];
				$this->formParams['last_amount_formatted'] = EmailForm::amountFormatter(
					(float)$contribution['amount'], $locale, $contribution['currency']
				);
				$this->formParams['last_receive_date_formatted'] = EmailForm::dateFormatter(
					$contribution['receive_date']
				);
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
		$this->formParams['recurringContributions'] = [];
		$pauseLink = '<a href="#" class="pause-donation">' . $this->msg( 'donorportal-recurring-pause' )->text() . '</a>';
		$cancelLink = '<a href="#" class="cancel-donation">' . $this->msg( 'donorportal-recurring-cancel' )->text() . '</a>';

		foreach ( $recurringContributions as $recurringContribution ) {
			if ( in_array(
				$recurringContribution['status'], [ 'In Progress', 'Pending', 'Failing', 'Processing', 'Overdue' ]
			) ) {
				$this->formParams['hasActiveRecurring'] = true;
				$recurringContribution['pause_link'] = $pauseLink;
				$recurringContribution['cancel_link'] = $cancelLink;
			}
			if ( in_array(
				$recurringContribution['status'], [ 'Completed', 'Failed', 'Cancelled' ]
			) ) {
				$this->formParams['hasInactiveRecurring'] = true;
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

			$this->formParams['recurringContributions'][] = $recurringContribution;
			// TODO: localize payment methods
		}
	}

}
