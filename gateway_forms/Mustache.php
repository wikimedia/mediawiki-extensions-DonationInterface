<?php

use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;

/**
 * Gateway form rendering using Mustache
 */
class Gateway_Form_Mustache extends Gateway_Form {

	const EXTENSION = '.html.mustache';

	/**
	 * We set the following public static variables for use in mustache helper
	 * functions l10n and fieldError, which need to be static and are interpreted
	 * without class scope under PHP 5.3's closure rules.
	 */
	public static $country;

	public static $fieldErrors;

	public static $baseDir;

	public function setGateway( GatewayType $gateway ) {
		parent::setGateway( $gateway );

		// FIXME: late binding fail?
		self::$baseDir = dirname( $this->getTopLevelTemplate() );
	}

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throw RuntimeException
	 */
	public function getForm() {
		$data = $this->getData();
		self::$country = $data['country'];

		$data = $data + $this->getErrors();
		$data = $data + $this->getUrls();

		self::$fieldErrors = $data['errors']['field'];

		// FIXME: is this really necessary for rendering retry links?
		if ( isset( $data['ffname'] ) ) {
			$this->gateway->session_pushFormName( $data['ffname'] );
		}

		$options = array(
			'helpers' => array(
				'l10n' => 'Gateway_Form_Mustache::l10n',
				'fieldError' => 'Gateway_Form_Mustache::fieldError',
			),
			'basedir' => array( self::$baseDir ),
			'fileext' => self::EXTENSION,
		);
		return self::render( $this->getTopLevelTemplate(), $data, $options );
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

		if ( !file_exists( $fileName ) ) {
			throw new RuntimeException( "Template file unavailable: [$fileName]" );
		}
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
			throw new RuntimeException(
				"Can't run compiled template! Template: '$code'"
			);
		}

		$html = call_user_func( $renderer, $data, array() );

		return $html;
	}

	protected function getData() {
		$data = $this->gateway->getData_Unstaged_Escaped();
		$output = $this->gatewayPage->getContext()->getOutput();

		$data['script_path'] = $this->scriptPath;
		$relativePath = $this->sanitizePath( $this->getTopLevelTemplate() );
		$data['template_trail'] = "<!-- Generated from: $relativePath -->";
		$data['action'] = $this->getNoCacheAction();

		$redirect = $this->gateway->getGlobal( 'NoScriptRedirect' );
		$data['no_script_redirect'] = $redirect;

		// FIXME: Appeal rendering should be broken out into its own thing.
		$appealWikiTemplate = $this->gateway->getGlobal( 'AppealWikiTemplate' );
		$appealWikiTemplate = str_replace( '$appeal', $data['appeal'], $appealWikiTemplate );
		$appealWikiTemplate = str_replace( '$language', $data['language'], $appealWikiTemplate );
		$data['appeal_text'] = $output->parse( '{{' . $appealWikiTemplate . '}}' );
		$data['is_cc'] = ( $this->gateway->getPaymentMethod() === 'cc' );

		$this->addSubmethods( $data );
		$this->addRequiredFields( $data );
		$this->addCurrencyData( $data );
		$data['recurring'] = (bool)$data['recurring'];
		return $data;
	}

	protected function addSubmethods( &$data ) {
		$availableSubmethods = $this->gateway->getAvailableSubmethods();
		$data['show_submethods'] = ( count( $availableSubmethods ) > 1 );
		if ( $data['show_submethods'] ) {
			// Need to add submethod key to its array 'cause mustache doesn't get keys
			$data['submethods'] = array();
			foreach ( $availableSubmethods as $key => $submethod ) {
				$submethod['key'] = $key;
				if ( isset( $submethod['logo'] ) ) {
					$submethod['logo'] = $this->getImagePath( $submethod['logo'] );
				}
				$submethod['srcset'] = $this->getSrcSet( $submethod );
				$data['submethods'][] = $submethod;
			}

			$data['button_class'] = count( $data['submethods'] ) % 4 === 0
				? 'four-per-line'
				: 'three-per-line';
		} elseif ( count( $availableSubmethods ) > 0 ) {
			$submethodNames = array_keys( $availableSubmethods );
			$submethodName = $submethodNames[0];
			$submethod = $availableSubmethods[$submethodName];
			$data['submethod'] = $submethodName;

			if (
				isset( $submethod['logo'] ) &&
				!empty( $submethod['show_single_logo'] )
			) {
				$data['show_single_submethod'] = true;
				$data['label'] = $submethod['label'];
				$data['submethod_logo'] = $this->getImagePath( $submethod['logo'] );
				$data['submethod_srcset'] = $this->getSrcSet( $submethod );
			}

			if ( isset( $submethod['issuerids'] ) ) {
				$data['show_issuers'] = true;
				$data['issuers'] = array();
				foreach ( $submethod['issuerids'] as $code => $label ) {
					$data['issuers'][] = array(
						'code' => $code,
						'label' => $label,
					);
				}
			}
		}
	}

	protected function getSrcSet( $submethod ) {
		if ( empty( $submethod['logo_hd'] ) ) {
			return '';
		}
		$srcSet = array();
		foreach ( $submethod['logo_hd'] as $scale => $filename ) {
			$path = $this->getImagePath( $filename );
			$srcSet[] = "$path $scale";
		}
		return 'srcset="' . implode( ',', $srcSet ) . '" ';
	}

	protected function addRequiredFields( &$data ) {
		// If any of these are required, show the address block
		$address_fields = array(
			'city',
			'state_province',
			'postal_code',
			'street_address',
		);
		$address_field_count = 0;
		$required_fields = $this->gateway->getRequiredFields();
		$data['show_personal_fields'] = !empty( $required_fields );
		foreach ( $required_fields as $field ) {
			$data["{$field}_required"] = true;

			if ( in_array( $field, $address_fields ) ) {
				$data['address_required'] = true;
				if ( $field !== 'street_address' ) {
					// street gets its own line
					$address_field_count++;
				}
			}
		}

		if ( !empty( $data['address_required'] ) ) {
			$classes = array(
				0 => 'fullwidth',
				1 => 'fullwidth',
				2 => 'halfwidth',
				3 => 'thirdwidth'
			);
			$data['address_css_class'] = $classes[$address_field_count];
			if ( !empty( $data['state_province_required'] ) ) {
				$this->setStateOptions( $data );
			}
		}
	}

	protected function setStateOptions( &$data ) {
		$state_list = Subdivisions::getByCountry( $data['country'] );
		$data['state_province_options'] = array();

		foreach ( $state_list as $abbr => $name ) {
			$selected = isset( $data['state_province'] )
				&& $data['state_province'] === $abbr;

			$data['state_province_options'][] = array(
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
			$data['currency'] = $supportedCurrencies[0];
		} else {
			$data['show_currency_selector'] = true;
		}
		foreach ( $supportedCurrencies as $currency ) {
			$data['currencies'][] = array(
				'code' => $currency,
				'selected' => ( $currency === $data['currency'] ),
			);
		}

		$data['display_amount'] = Amount::format(
			$data['amount'],
			$data['currency'],
			$data['language'] . '_' . $data['country']
		);
	}

	/**
	 * Get errors, sorted into two buckets - 'general' errors to display at
	 * the top of the form, and 'field' errors to display inline.
	 * Also get some error-related flags.
	 * @return array
	 */
	protected function getErrors() {
		$errors = $this->gateway->getErrorState()->getErrors();
		$return = array( 'errors' => array(
			'general' => array(),
			'field' => array(),
		) );
		$fieldNames = DonationData::getFieldNames();
		foreach ( $errors as $error ) {
			if ( $error instanceof ValidationError ) {
				$key = $error->getField();

				$message = MessageUtils::getCountrySpecificMessage(
					$error->getMessageKey(),
					self::$country,
					RequestContext::getMain()->getLanguage()->getCode(),
					$error->getMessageParams()
				);
			} elseif ( $error instanceof PaymentError ) {
				$key = $error->getErrorCode();
				$message = $this->gateway->getErrorMapByCodeAndTranslate( $error->getErrorCode() );
			} else {
				throw new RuntimeException( "Unknown error type: " . var_export( $error, true ) );
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

			// FIXME: Belongs in a separate phase?
			if ( $key === 'currency' || $key === 'amount' ) {
				$return['show_amount_input'] = true;
			}
			if ( !empty( $return['errors']['general'] ) ) {
				$return['show_error_reference'] = true;
			}
		}
		return $return;
	}

	protected function getUrls() {
		return array(
			'problems_url' => htmlspecialchars( $this->gateway->localizeGlobal( 'ProblemsURL' ) ),
			'otherways_url' => htmlspecialchars( $this->gateway->localizeGlobal( 'OtherWaysURL' ) ),
			'faq_url' => htmlspecialchars( $this->gateway->localizeGlobal( 'FaqURL' ) ),
			'tax_url' => htmlspecialchars( $this->gateway->localizeGlobal( 'TaxURL' ) ),
			'policy_url' => htmlspecialchars( $this->gateway->localizeGlobal( 'PolicyURL' ) ),
		);
	}

	// For the following helper functions, we can't use self:: to refer to
	// static variables (under PHP 5.3), so we use Gateway_Form_Mustache::

	/**
	 * Get a message value specific to the donor's country and language.
	 *
	 * @param array $params first value is used as message key
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
			self::$country,
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

		if ( isset( self::$fieldErrors[$fieldName] ) ) {
			$context = self::$fieldErrors[$fieldName];
			$context['cssClass'] = 'errorMsg';
		} else {
			$context = array(
				'cssClass' => 'errorMsgHide',
				'key' => $fieldName,
			);
		}

		$path = self::$baseDir . DIRECTORY_SEPARATOR
			. 'error_message' . self::EXTENSION;

		return self::render( $path, $context );
	}

	public function getStyleModules() {
		return 'ext.donationInterface.mustache.styles';
	}

	protected function getTopLevelTemplate() {
		return $this->gateway->getGlobal( 'Template' );
	}

	protected function getImagePath( $name ) {
		return "{$this->scriptPath}/extensions/DonationInterface/gateway_forms/includes/{$name}";
	}
}
