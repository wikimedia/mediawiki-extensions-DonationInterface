<?php

/**
 * Gateway form rendering using Mustache
 */
class Gateway_Form_Mustache extends Gateway_Form {
	/**
	 * @var string Janky way to keep track of the template file path that will
	 * be used as the main entry point for rendering.
	 */
	protected $topLevelForm;

	const EXTENSION = '.html.mustache';

	/**
	 * We set the following public static variables for use in mustache helper
	 * functions l10n and fieldError, which need to be static and are interpreted
	 * without class scope under PHP 5.3's closure rules.
	 */
	public static $country;

	public static $fieldErrors;

	public static $baseDir;

	/**
	 * @param GatewayAdapter $gateway The live adapter object that is used as
	 * the source for donor data and capabilities discovery.
	 */
	public function __construct( GatewayAdapter $gateway ) {
		parent::__construct( $gateway );

		// TODO: Don't hardcode like this.
		global $wgDonationInterfaceTemplate;
		$this->topLevelForm = $wgDonationInterfaceTemplate;
		self::$baseDir = dirname( $this->topLevelForm );
	}

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throw RuntimeException
	 */
	public function getForm() {
		$data = $this->getData();
		$data = $data + $this->getErrors();
		$data = $data + $this->getUrls();

		self::$country = $data['country'];
		self::$fieldErrors = $data['errors']['field'];

		$options = array(
			'helpers' => array(
				'l10n' => 'Gateway_Form_Mustache::l10n',
				'fieldError' => 'Gateway_Form_Mustache::fieldError',
			),
			'basedir' => array( self::$baseDir ),
			'fileext' => self::EXTENSION,
		);
		return self::render( $this->topLevelForm, $data, $options );
	}

	/**
	 * Do the rendering. Can be made protected when we're off PHP 5.3.
	 *
	 * @param string $fileName full path to template file
	 * @param array $data rendering context
	 * @param array $options options for LightnCandy::compile function
	 * @return string rendered template
	 */
	public static function render( $fileName, $data, $options = array() ) {
		$defaultOptions = array(
			'flags' => LightnCandy::FLAG_ERROR_EXCEPTION,
		);

		$options = $options + $defaultOptions;

		$template = file_get_contents( $fileName );
		if ( $template === false ) {
			throw new RuntimeException( "Template file unavailable: [$fileName]" );
		}

		// TODO: Use MW-core implementation once it allows helper functions
		$code = LightnCandy::compile( $template, $options );
		if ( !$code ) {
			throw new RuntimeException( 'Couldn\'t compile template!' );
		}
		if ( substr( $code, 0, 5 ) === '<?php' ) {
			$code = substr( $code, 5 );
		}
		$renderer = eval( $code );
		if ( !is_callable( $renderer ) ) {
			throw new RuntimeException( 'Can\'t run compiled template!' );
		}

		$html = call_user_func( $renderer, $data, array() );

		return $html;
	}

	protected function getData() {
		$data = $this->gateway->getData_Unstaged_Escaped();
		$output = $this->gateway->getContext()->getOutput();

		$data['script_path'] = $this->scriptPath;
		$data['verisign_logo'] = $this->getSmallSecureLogo();
		$relativePath = $this->sanitizePath( $this->topLevelForm );
		$data['template_trail'] = "<!-- Generated from: $relativePath -->";
		$data['action'] = $this->getNoCacheAction();

		$redirect = $this->gateway->getGlobal( 'NoScriptRedirect' );
		$data['no_script_redirect'] = $redirect;
		$data['has_no_script_redirect'] = isset( $redirect ); // grr

		$appealWikiTemplate = $this->gateway->getGlobal( 'AppealWikiTemplate' );
		$appealWikiTemplate = str_replace( '$appeal', $data['appeal'], $appealWikiTemplate );
		$appealWikiTemplate = str_replace( '$language', $data['language'], $appealWikiTemplate );
		$data['appeal_text'] = $output->parse( '{{' . $appealWikiTemplate . '}}' );

		$availableSubmethods = $this->gateway->getAvailableSubmethods();
		$data['show_submethods'] = ( count( $availableSubmethods ) > 1 );
		if ( $data['show_submethods'] ) {
			// Need to add submethod key to its array 'cause mustache doesn't get keys
			$data['submethods'] = array();
			foreach ( $availableSubmethods as $key => $submethod ) {
				$submethod['key'] = $key;
				if ( isset( $submethod['logo'] ) ) {
					$submethod['logo'] = "{$data['script_path']}/extensions/DonationInterface/gateway_forms/includes/{$submethod['logo']}";
				}
				if ( isset( $submethod['sub_text_key'] ) ) {
					$submethod['has_sub_text'] = true;
				}
				$data['submethods'][] = $submethod;
			}

			$data['button_class'] = count( $data['submethods'] ) % 4 === 0
				? 'four-per-line'
				: 'three-per-line';
		} else if ( count( $availableSubmethods ) > 0 ) {
			$submethodNames = array_keys( $availableSubmethods );
			$data['submethod'] = $submethodNames[0];
		}
		$data['is_cc'] = ( $this->gateway->getPaymentMethod() === 'cc' );

		$this->addRequiredFields( $data );
		$this->addCurrencyData( $data );
		$data['recurring'] = (bool) $data['recurring'];
		return $data;
	}

	protected function addRequiredFields( &$data ) {
		// If any of these are required, show the address block
		$address_fields = array(
			'city',
			'state',
			'zip',
			'street',
		);
		$required_fields = $this->gateway->getRequiredFields();
		foreach( $required_fields as $field ) {
			$data["{$field}_required"] = true;

			if ( in_array( $field, $address_fields ) ) {
				$data['address_required'] = true;
			}
		}

		if ( !empty( $data['address_required'] ) ) {
			$data['address_css_class'] = 'halfwidth';
			if ( !empty( $data['street_required'] ) ) {
				$this->setStateOptions( $data );
			}
		}
	}

	protected function setStateOptions( &$data ) {
		$state_list = Subdivisions::getByCountry( $data['country'] );
		$data['address_css_class'] = 'thirdwidth';
		$data['state_options'] = array();

		foreach ( $state_list as $abbr => $name ) {
			$selected = isset( $data['state'] )
				&& $data['state'] === $abbr;

			$data['state_options'][] = array(
				'abbr' => $abbr,
				'name' => $name,
				'selected' => $selected,
			);
		}
	}

	protected function addCurrencyData( &$data ) {
		$supportedCurrencies = $this->gateway->getCurrencies();
		if ( count( $supportedCurrencies ) === 1 ) {
			$data['show_currency_selector'] = false;
			// The select input will be hidden, but posting the form will use its only value
			// Display the same currency code
			$data['currency_code'] = $supportedCurrencies[0];
		} else {
			$data['show_currency_selector'] = true;
		}
		foreach( $supportedCurrencies as $currency ) {
			$data['currencies'][] = array(
				'code' => $currency,
				'selected' => ( $currency === $data['currency_code'] ),
			);
		}
		if ( class_exists( 'NumberFormatter' ) ) {
			$locale = $data['language'] . '_' . $data['country'];
			$formatter = new NumberFormatter( $locale, NumberFormatter::CURRENCY );
			$data['display_amount'] = $formatter->formatCurrency(
				$data['amount'],
				$data['currency_code']
			);
		} else {
			$data['display_amount'] = "{$data['amount']} {$data['currency_code']}";
		}
	}

	/**
	 * Get errors, sorted into two buckets - 'general' errors to display at
	 * the top of the form, and 'field' errors to display inline.
	 * Also get some error-related flags.
	 * @return array
	 */
	protected function getErrors() {
		$errors = $this->gateway->getAllErrors();
		$return = array( 'errors' => array(
			'general' => array(),
			'field' => array(),
		) );
		$fieldNames = DonationData::getFieldNames();
		foreach( $errors as $key => $error ) {
			if ( is_array( $error ) ) {
				// TODO: set errors consistently
				$message = implode( '<br/>', $error );
			} else {
				$message = $error;
			}
			$errorContext = array(
				'key' => $key,
				'message' => $message,
			);
			if ( in_array( $key, $fieldNames ) ) {
				$return['errors']['field'][$key] = $errorContext;
			} else {
				$return['errors']['general'][] = $errorContext;
			}
			$return["{$key}_error"] = true;
			if ( $key === 'currency_code' || $key === 'amount' ) {
				$return['show_amount_input'] = true;
			}
		}
		return $return;
	}

	protected function getUrls() {
		return array(
			'problems_url' => $this->gateway->localizeGlobal( 'ProblemsURL' ),
			'otherways_url' => $this->gateway->localizeGlobal( 'OtherWaysURL' ),
			'faq_url' => $this->gateway->localizeGlobal( 'FaqURL' ),
			'tax_url' => $this->gateway->localizeGlobal( 'TaxURL' ),
		);
	}

	// For the following helper functions, we can't use self:: to refer to
	// static variables (under PHP 5.3), so we use Gateway_Form_Mustache::

	/**
	 * Get a message value specific to the donor's country and language.
	 *
	 * @param array $params first value is used as message key
	 * TODO: use the rest as message parameters
	 * @return string
	 */
	public static function l10n( $params ) {
		if ( !$params ) {
			throw new BadMethodCallException( 'Need at least one message key' );
		}
		$language = RequestContext::getMain()->getLanguage()->getCode();
		$key = array_shift( $params );
		return MessageUtils::getCountrySpecificMessage(
			$key,
			Gateway_Form_Mustache::$country,
			$language,
			$params
		);
	}

	/**
	 * Render a validation error message or blank error placeholder.
	 *
	 * @param array $params first should be the field name
	 * @return string
	 */
	public static function fieldError( $params ) {
		if ( !$params ) {
			throw new BadMethodCallException( 'Need field key' );
		}

		$fieldName = array_shift( $params );

		if ( isset( Gateway_Form_Mustache::$fieldErrors[$fieldName] ) ) {
			$context = Gateway_Form_Mustache::$fieldErrors[$fieldName];
			$context['cssClass'] = 'errorMsg';
		} else {
			$context = array(
				'cssClass' => 'errorMsgHide',
				'key' => $fieldName,
			);
		}

		$path = Gateway_Form_Mustache::$baseDir . DIRECTORY_SEPARATOR
			. 'error_message' . Gateway_Form_Mustache::EXTENSION;

		return Gateway_Form_Mustache::render( $path, $context );
	}

	public function getResources() {
		return array(
			'ext.donationinterface.mustache.styles',
		);
	}
}
