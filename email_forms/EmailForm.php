<?php

use LightnCandy\LightnCandy;

class EmailForm {

	/**
	 * @var string
	 */
	protected $process;

	const EXTENSION = '.html.mustache';

	/**
	 * @var array
	 */
	protected $params;

	public function __construct( $process, $params = [] ) {
		$this->process = $process;
		$this->params = $params;
	}

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throws RuntimeException
	 */
	public function getForm() {
		$options = [
			'helpers' => [
				'l10n' => 'EmailForm::l10n',
				'dateFormatter' => 'EmailForm::dateFormatter',
				'amountFormatter' => 'EmailForm::amountFormatter',
			],
			'basedir' => __DIR__ . '/templates/',
			'fileext' => self::EXTENSION,
			'options' => LightnCandy::FLAG_RUNTIMEPARTIAL,
		];

		$options['partials'] = [
			'emailPreferencesHeader' => rtrim( file_get_contents( $options['basedir'] . 'emailPreferencesHeader' .
				$options['fileext'] ), "\r\n" ),
			'emailPreferencesFooter' => rtrim( file_get_contents( $options['basedir'] . 'emailPreferencesFooter' .
				$options['fileext'] ), "\r\n" ),
		];

		if ( empty( $this->params['variant'] ) ) {
			$variant = '';
		} else {
			$variant = '_' . $this->params['variant'];
		}
		$fileName = $this->process . $variant . self::EXTENSION;
		$templatePath = $options['basedir'] . $fileName;
		if ( !file_exists( $templatePath ) && $variant !== '' ) {
			// Fall back to non-variant version if the variant doesn't exist
			$fileName = $this->process . self::EXTENSION;
			$templatePath = $options['basedir'] . $fileName;
		}
		$templateParams = $this->getTemplateParams();

		return MustacheHelper::render( $templatePath, $templateParams, $options );
	}

	public static function l10n( ...$params ) {
		$key = array_shift( $params );
		return call_user_func_array(
			'wfMessage',
			[ $key, MustacheHelper::filterMessageParams( $params ) ]
		)->text();
	}

	public static function dateFormatter( $dateString ) {
		$date = ( new DateTime( $dateString ) )->format( 'F jS, Y' );
		return $date;
	}

	public static function amountFormatter( $amount, $locale, $currency ) {
		return Amount::format(
			$amount,
			$currency,
			$locale
		);
	}

	protected function getTemplateParams() {
		global $wgDonationInterfaceEmailFormHelpEmail;

		$paramList = [
			'checksum',
			'contact_id',
			'countries',
			'currency',
			'dontSendEmail',
			'email',
			'first_name',
			'full_name',
			'language',
			'languages',
			'locale',
			'new_amount_formatted',
			'next_sched_date',
			'next_sched_date_formatted',
			'sendEmail',
			'token',
			'recur_amount',
			'recurringOptions',
			'recur_amount_formatted',
			'variant',
		];
		$templateParams = [];

		foreach ( $paramList as $paramName ) {
			if ( isset( $this->params[$paramName] ) ) {
				$templateParams[$paramName] = $this->params[$paramName];
			}
		}

		$context = RequestContext::getMain();

		if ( !isset( $templateParams['token'] ) ) {
			$session = $context->getRequest()->getSession();
			$templateParams['token'] = $session->getToken()->toString();
			$session->persist();
		}

		$templatePath = $context->getConfig()->get( 'ScriptPath' ) .
			'/extensions/DonationInterface/email_forms/templates';
		$languageCode = $context->getLanguage()->getCode();

		$templateParams += [
			'uselang' => $languageCode,
			'template_path' => $templatePath,
			'help_email' => $wgDonationInterfaceEmailFormHelpEmail,
			'action' => $context->getRequest()->getFullRequestURL(),
			'policy_url' => $this->getPolicyUrl( $languageCode ),
		];
		return $templateParams;
	}

	protected function getPolicyUrl( $languageCode ) {
		global $wgDonationInterfacePolicyURL;

		// $wgDonationInterfacePolicyURL has $language and $country variables
		// to replace. We know language but not country.
		$policyUrl = str_replace(
			'$country',
			'',
			$wgDonationInterfacePolicyURL
		);

		$policyUrl = str_replace(
			'$language',
			$languageCode,
			$policyUrl
		);
		return $policyUrl;
	}
}
