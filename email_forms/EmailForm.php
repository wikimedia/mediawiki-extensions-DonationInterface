<?php

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
			],
			'basedir' => [ __DIR__ . '/templates' ],
			'fileext' => self::EXTENSION,
		];
		if ( empty( $this->params['variant'] ) ) {
			$variant = '';
		} else {
			$variant = '_' . $this->params['variant'];
		}
		$fileName = $this->process . $variant . self::EXTENSION;
		$templatePath = __DIR__ . '/templates/' . $fileName;
		if ( !file_exists( $templatePath ) && $variant !== '' ) {
			// Fall back to non-variant version if the variant doesn't exist
			$fileName = $this->process . self::EXTENSION;
			$templatePath = __DIR__ . '/templates/' . $fileName;
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

	protected function getTemplateParams() {
		# FIXME Switch to a DonationInterface setting
		global $wgFundraisingEmailUnsubscribeHelpEmail;

		$paramList = [
			'contact_hash', 'contact_id', 'email', 'token', 'variant', 'first_name', 'countries',
				'languages', 'sendEmail', 'dontSendEmail'
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
			'help_email' => $wgFundraisingEmailUnsubscribeHelpEmail,
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
