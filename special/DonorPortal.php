<?php

use MediaWiki\Extension\DonationInterface\Validation\AmountHelper;
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

		if ( !$this->getConfig()->has( 'DonorPortalMockData' ) && $this->isChecksumExpired() ) {
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
		$config = $this->getConfig();
		// Call the (renamed) function from RequestNewChecksumLinkTrait
		if ( !$config->has( 'DonorPortalMockData' ) ) {
			$this->setChecksumClientVariables( $vars );
		}
		$vars['donorData'] = $this->formParams;
		$vars['help_email'] = $this->getConfig()->get( 'DonationInterfaceEmailFormHelpEmail' );
		$vars['recurringUpgradeMaxUSD'] = $this->getConfig()->get( 'DonationInterfaceRecurringUpgradeMaxUSD' );
		$vars['emailPreferencesUrl'] = $this->getPreferencesUrl( $this->formParams );
		$vars['endowmentLearnMoreUrl'] = $config->get( 'DonationInterfaceEndowmentLearnMoreURL' );
		$vars['endowmentDonationUrl'] = $config->get( 'DonationInterfaceEndowmentDonationURL' );
		$vars['donorFaqUrl'] = $config->get( 'DonationInterfaceFaqURL' );
		$vars['otherWaysUrl'] = str_replace(
			'$language',
			$this->getLanguage()->getCode(),
			$config->get( 'DonationInterfaceOtherWaysURL' )
		);
		$vars['legacyUrl'] = $config->get( 'DonationInterfaceLegacyURL' );
		$vars['surveyUrl'] = $config->get( 'DonationInterfaceSurveyURL' );
		$vars['newDonationUrl'] = $config->get( 'DonationInterfaceNewDonationURL' ) .
			'?wmf_medium=donorportal&wmf_campaign=donorportal&wmf_source=donorportal' .
			'&contact_id=' . $this->getContactId() .
			'&checksum=' . $this->getChecksum();
		$vars['requestDonorPortalPage'] = $this->getPageTitle()->getBaseText();
		$vars['wikipediaVideoSources'] = $config->get( 'DonationInterfaceWikipediaVideoSources' );
		$vars['wikipediaVideoCommonsUrl'] = $config->get( 'DonationInterfaceWikipediaVideoCommonsUrl' );
	}

	protected function getContactID(): int {
		return $this->getRequest()->getInt( 'contact_id' );
	}

	protected function getChecksum(): string {
		$checksum = $this->getRequest()->getVal( 'checksum' );
		if ( !$checksum || !preg_match( '/^[0-9a-f]{32}_[0-9]{10}_[0-9a-z]+$/', $checksum ) ) {
			return '';
		}
		return $checksum;
	}

	/**
	 * Fetches donor data from Civiproxy and assigns template paramates to
	 * $this->formParams
	 *
	 * @return void
	 */
	private function assignFormParameters(): void {
		if ( $this->getConfig()->has( 'DonorPortalMockData' ) ) {
			$this->formParams = $this->getConfig()->get( 'DonorPortalMockData' );
			return;
		}
		$requestParameters = $this->getSanitizedRequestParameters();
		$donorSummary = CiviproxyConnect::getDonorSummary(
			$requestParameters['checksum'],
			$requestParameters['contact_id']
		);
		// if civiproxy returned an error, show the login form instead
		if ( !isset( $donorSummary['id'] ) ) {
			$this->formParams = [ 'showLogin' => true ];
			return;
		}
		$locale = $this->getLocale( $donorSummary );
		$this->formParams = $donorSummary;
		$this->addContributionsToFormParams( $donorSummary['contributions'] ?? [], $locale );
		$this->addRecurringContributionsToFormParams( $donorSummary['recurringContributions'] ?? [], $locale );

		$this->formParams['donorID'] = 'CNTCT-' . $donorSummary['id'];
		$this->formParams = array_merge( $this->formParams, $requestParameters );
	}

	private function getSanitizedRequestParameters(): array {
		return [
			'checksum' => $this->getChecksum(),
			'contact_id' => $this->getContactID()
		];
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
		$amountHelper = new AmountHelper( $this->getConfig() );

		foreach ( $recurringContributions as $recurringContribution ) {
			if ( in_array(
				$recurringContribution['status'], [ 'In Progress', 'Pending', 'Failing', 'Processing', 'Overdue' ]
			) ) {
				$this->formParams['hasActiveRecurring'] = true;
				$key = 'recurringContributions';

				if ( $recurringContribution['frequency_unit'] === 'month' ) {
					// Consider it paused if the next charge date is more than 31 days in the future
					$recurringContribution['is_paused'] = (
						new \DateTime( $recurringContribution['next_sched_contribution_date'] ) >
						new \DateTime( '+31 days' )
					);
				} elseif ( $recurringContribution['frequency_unit'] === 'year' ) {
					// Consider it paused if the next charge date is more than 31 days in the future
					$recurringContribution['is_paused'] = (
						new \DateTime( $recurringContribution['next_sched_contribution_date'] ) >
						new \DateTime( '+366 days' )
					);
				}
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
			$recurringContribution['currency_symbol'] = trim(
				preg_replace( '/[0-9.,\s]/', '', $recurringContribution['amount_formatted'] )
			);
			$recurringContribution['next_sched_contribution_date_formatted'] = EmailForm::dateFormatter(
				$recurringContribution['next_sched_contribution_date'] ?? ''
			);
			$recurringContribution['next_contribution_date_yearly'] = date( 'Y-m-d h:m:s', strtotime( '+11 months',
					strtotime( $recurringContribution['next_sched_contribution_date'] ) ) );

			$recurringContribution['next_contribution_date_yearly_formatted'] = EmailForm::dateFormatter(
				$recurringContribution['next_contribution_date_yearly']
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

			if ( $recurringContribution['can_modify'] && $key == 'recurringContributions' ) {
				$recurringContribution['donation_rules'] = $amountHelper->getDonationRules(
					$recurringContribution['payment_processor'],
					[
						'country' => $recurringContribution['country'],
						'currency' => $recurringContribution['currency'],
						'recurring' => true,
					]
				);
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

		$out->addLink( [
			'rel' => 'dns-prefetch',
			'href' => 'https://upload.wikimedia.org'
		] );
	}

	/**
	 * Get a link to the Email Preferences special page, or null if configured
	 * not to display
	 * @param array $formParameters
	 * @return string|null
	 */
	protected function getPreferencesUrl( array $formParameters ): ?string {
		if ( !$this->getConfig()->get( 'DonationInterfaceShowEmailPreferencesLink' ) ) {
			return null;
		}
		$title = self::getTitleFor( 'EmailPreferences', 'emailPreferences' );
		return $title->getLocalUrl( [
			'contact_id' => $formParameters['contact_id'] ?? null,
			'checksum' => $formParameters['checksum'] ?? null,
		] );
	}
}
