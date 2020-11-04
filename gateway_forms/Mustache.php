<?php

use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use LightnCandy\LightnCandy;

/**
 * Gateway form rendering using Mustache
 */
class Gateway_Form_Mustache extends Gateway_Form {

	const EXTENSION = '.html.mustache';

	/**
	 * We set the following public static variables for use in mustache helper
	 * functions l10n and fieldError, which need to be static and are accessed
	 * via eval()'ed code from MustacheHelper.
	 */
	// phpcs:disable Squiz.Classes.SelfMemberReference.NotUsed
	public static $country;

	public static $fieldErrors;

	public static $baseDir;

	protected static $partials = [
		'issuers',
		'more_info_links',
		'no_script',
		'opt_in',
		'payment_amount',
		'payment_method',
		'personal_info',
		'monthly_convert',
		'state_dropdown'
	];

	/**
	 * @var array Keys are message keys used in templates, values are
	 *  message keys to replace them with.
	 */
	public static $messageReplacements = [];

	public function setGateway( GatewayType $gateway ) {
		parent::setGateway( $gateway );

		// FIXME: late binding fail?
		self::$baseDir = dirname( $this->getTopLevelTemplate() );
		$replacements = $gateway->getConfig( 'message_replacements' );
		if ( $replacements ) {
			self::$messageReplacements = $replacements;
		}
	}

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throws RuntimeException
	 */
	public function getForm() {
		$data = $this->getData();
		self::$country = $data['country'];

		$data = $data + $this->getErrors();
		$data = $data + $this->getUrlsAndEmails();

		self::$fieldErrors = $data['errors']['field'];

		// FIXME: is this really necessary for rendering retry links?
		if ( isset( $data['ffname'] ) ) {
			$this->gateway->session_pushFormName( $data['ffname'] );
		}

		$options = [
			'helpers' => [
				'l10n' => 'Gateway_Form_Mustache::l10n',
				'fieldError' => 'Gateway_Form_Mustache::fieldError',
			],
			'basedir' => [ self::$baseDir ],
			'fileext' => self::EXTENSION,
			'partials' => $this->getPartials( $data ),
			'options' => LightnCandy::FLAG_RUNTIMEPARTIAL,
		];
		return MustacheHelper::render( $this->getTopLevelTemplate(), $data, $options );
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
		$data['appeal_text'] = $output->parseAsContent( '{{' . $appealWikiTemplate . '}}' );
		$data['is_cc'] = ( $this->gateway->getPaymentMethod() === 'cc' );

		// Only render monthly convert when we come back from a qualified processor
		if (
			$this->gateway->showMonthlyConvert() &&
			$this->gatewayPage instanceof ResultSwitcher
		) {
			$data['monthly_convert'] = true;
		}

		$this->addSubmethods( $data );
		$this->addFormFields( $data );
		$this->handleOptIn( $data );
		$this->addCurrencyData( $data );
		$data['recurring'] = (bool)$data['recurring'];
		return $data;
	}

	protected function handleOptIn( &$data ) {
		// Since this value can be 1, 0, or unset, we need to make
		// special conditionals for the mustache logic
		if ( !isset( $data['opt_in'] ) || $data['opt_in'] === '' ) {
			return;
		}
		$hasValidValue = false;
		switch ( (string)$data['opt_in'] ) {
			case '1':
				$data['opted_in'] = true;
				$hasValidValue = true;
				break;
			case '0':
				$data['opted_out'] = true;
				$hasValidValue = true;
				break;
			default:
				$logger = DonationLoggerFactory::getLogger(
					$this->gateway,
					'',
					$this->gateway
				);
				$logger->warning( "Invalid opt_in value {$data['opt_in']}" );
				break;
		}
		// If we have a valid value passed in on the query string, don't
		// show the radio buttons to the user (they've already seen them
		// in the banner or on donatewiki)
		// If the value came from 'post' we may be re-rendering a form
		// with some kind of validation error and should keep showing
		// the opt_in radio buttons.
		$dataSources = $this->gateway->getDataSources();
		if ( $hasValidValue && $dataSources['opt_in'] === 'get' ) {
			// assuming it's always going to be '_visible' isn't safe, see comment on L234
			$data['opt_in_visible'] = false;
		}
	}

	protected function addSubmethods( &$data ) {
		$availableSubmethods = $this->gateway->getAvailableSubmethods();
		$showPresetSubmethod = !empty( $data['payment_submethod'] ) &&
			array_key_exists( $data['payment_submethod'], $availableSubmethods );

		// if the payment_submethod is not sent explicitly via the query string let's
		// assume the user will benefit from seeing all available options
		if ( $this->gateway->getDataSources()['payment_submethod'] != 'get'
			&& $showPresetSubmethod ) {
			$showPresetSubmethod = false;
		}

		$showMultipleSubmethods = ( !$showPresetSubmethod && count( $availableSubmethods ) > 1 );
		$showSingleSubmethod = count( $availableSubmethods ) == 1;

		if ( $showMultipleSubmethods ) {
			$data['show_submethods'] = true;
			// Need to add submethod key to its array 'cause mustache doesn't get keys
			$data['submethods'] = [];
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
		} elseif ( $showSingleSubmethod || $showPresetSubmethod ) {

			$submethodName = ( $showPresetSubmethod ) ? $data['payment_submethod'] :
				array_keys( $availableSubmethods )[0];
			$submethod = $availableSubmethods[$submethodName];
			$data['submethod'] = $submethodName;

			if ( isset( $submethod['logo'] ) &&
				( $showPresetSubmethod || !empty( $submethod['show_single_logo'] ) ) ) {
				$data['show_single_submethod'] = true;
				$data['submethod_label_key'] = $submethod['label_key'] ?? false;
				$data['submethod_label'] = $submethod['label'] ?? false;
				$data['submethod_logo'] = $this->getImagePath( $submethod['logo'] );
				$data['submethod_srcset'] = $this->getSrcSet( $submethod );
			}

			if ( isset( $submethod['issuerids'] ) ) {
				$data['show_issuers'] = true;
				$data['issuers'] = [];
				foreach ( $submethod['issuerids'] as $code => $label ) {
					$data['issuers'][] = [
						'code' => $code,
						'label' => $label,
					];
				}
			}
		}
	}

	protected function getSrcSet( $submethod ) {
		if ( empty( $submethod['logo_hd'] ) ) {
			return '';
		}
		$srcSet = [];
		foreach ( $submethod['logo_hd'] as $scale => $filename ) {
			$path = $this->getImagePath( $filename );
			$srcSet[] = "$path $scale";
		}
		return 'srcset="' . implode( ',', $srcSet ) . '" ';
	}

	protected function addFormFields( &$data ) {
		// If any of these are required, show the address block
		$address_fields = [
			'city',
			'state_province',
			'postal_code',
			'street_address',
		];
		// These are shown outside of the 'Billing information' block
		$outside_personal_block = [
			'opt_in'
		];
		$show_personal_block = false;
		$address_field_count = 0;
		$fields = $this->gateway->getFormFields();
		foreach ( $fields as $field => $type ) {
			if ( $type === false ) {
				continue;
			}

			// if field type is true(required) or optional it should be visible
			if ( in_array( $type, [ true, 'optional' ], true ) ) {
				$data["{$field}_visible"] = true;
				if ( in_array( $field, $address_fields ) ) {
					$data["address_visible"] = true;
					if ( $field !== 'street_address' ) {
						// street gets its own line
						$address_field_count++;
					}
				}

				// if field type is true(required), we also inject a *_required var to inform the view
				if ( $type === true ) {
					$data["{$field}_required"] = true;
					if ( in_array( $field, $address_fields ) ) {
						$data["address_required"] = true;
					}
				}
			}

			if ( !in_array( $field, $outside_personal_block ) ) {
				$show_personal_block = true;
			}
		}

		$data['show_personal_fields'] = $show_personal_block;

		// this is not great, we're assuming 'visible' (previously 'required') will always be a thing.
		// the decision for the current _visible suffix is made on line 217
		if ( !empty( $data["address_visible"] ) ) {
			$classes = [
				0 => 'fullwidth',
				1 => 'fullwidth',
				2 => 'halfwidth',
				3 => 'thirdwidth'
			];
			$data['address_css_class'] = $classes[$address_field_count];
			// not great for the ranty reasons mentioned on line 234
			if ( !empty( $data["state_province_visible"] ) ) {
				$this->setStateOptions( $data );
			}
		}
	}

	protected function setStateOptions( &$data ) {
		$state_list = Subdivisions::getByCountry( $data['country'] );
		$data['state_province_options'] = [];

		foreach ( $state_list as $abbr => $name ) {
			$selected = isset( $data['state_province'] )
				&& $data['state_province'] === $abbr;

			$data['state_province_options'][] = [
				'abbr' => $abbr,
				'name' => $name,
				'selected' => $selected,
			];
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
			$data['currencies'][] = [
				'code' => $currency,
				'selected' => ( $currency === $data['currency'] ),
			];
		}

		$data['display_amount'] = Amount::format(
			$data['amount'],
			$data['currency'],
			$data['language'] . '_' . $data['country']
		);
		if ( doubleval( $data['amount'] ) === 0.0 ) {
			$data['amount'] = '';
		}
	}

	/**
	 * Get errors, sorted into two buckets - 'general' errors to display at
	 * the top of the form, and 'field' errors to display inline.
	 * Also get some error-related flags.
	 * @return array
	 */
	protected function getErrors() {
		$errors = $this->gateway->getErrorState()->getErrors();
		$return = [ 'errors' => [
			'general' => [],
			'field' => [],
		] ];
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

			$errorContext = [
				'key' => $key,
				'message' => $message,
			];

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

	protected function getUrlsAndEmails() {
		$map = [
			'problems' => 'Problems',
			'otherways' => 'OtherWays',
			'faq' => 'Faq',
			'tax' => 'Tax',
			'policy' => 'Policy'
		];
		$urlsAndEmails = [];
		foreach ( $map as $contextName => $globalName ) {
			$urlsAndEmails[$contextName . '_url'] = htmlspecialchars(
				$this->gateway->localizeGlobal( $globalName . 'URL' )
			);
		}
		$urlsAndEmails['problems_email'] = $this->gateway->getGlobal( 'ProblemsEmail' );
		return $urlsAndEmails;
	}

	// For the following helper functions, we can't use self:: to refer to
	// static variables since rendering happens in another class, so we use
	// Gateway_Form_Mustache::

	/**
	 * Get a message value specific to the donor's country and language.
	 *
	 * @param string $key message key
	 * @param array ...$params values to fill in message placeholders
	 * @return string
	 */
	public static function l10n( $key, ...$params ) {
		$language = RequestContext::getMain()->getLanguage()->getCode();
		// If there are any form variant messages configured swap them out here
		if ( isset( Gateway_Form_Mustache::$messageReplacements[$key] ) ) {
			$key = Gateway_Form_Mustache::$messageReplacements[$key];
		}
		// Sometimes Lightncandy seems to send us an array with way too much
		// information as the last param. Remove any params that are themselves
		// arrays, as our message formatting can't handle them.
		$filteredParams = [];
		foreach ( $params as $param ) {
			if ( is_scalar( $param ) ) {
				$filteredParams[] = $param;
			}
		}
		return MessageUtils::getCountrySpecificMessage(
			$key,
			Gateway_Form_Mustache::$country,
			$language,
			$filteredParams
		);
	}

	/**
	 * Render a validation error message or blank error placeholder.
	 *
	 * @param string $fieldName
	 * @return string
	 */
	public static function fieldError( $fieldName ) {
		if ( isset( Gateway_Form_Mustache::$fieldErrors[$fieldName] ) ) {
			$context = Gateway_Form_Mustache::$fieldErrors[$fieldName];
			$context['cssClass'] = 'errorMsg';
		} else {
			$context = [
				'cssClass' => 'errorMsgHide',
				'key' => $fieldName,
			];
		}

		$path = Gateway_Form_Mustache::$baseDir . DIRECTORY_SEPARATOR
			. 'error_message' . Gateway_Form_Mustache::EXTENSION;

		return MustacheHelper::render( $path, $context );
	}

	// phpcs:enable Squiz.Classes.SelfMemberReference.NotUsed

	public function getResources() {
		$resources = parent::getResources();
		$gatewayModules = $this->gateway->getConfig( 'ui_modules' );
		$this->addModules( 'scripts', $resources, $gatewayModules );
		if ( $this->gateway->getGlobal( 'LogClientErrors' ) ) {
			$resources[] = 'ext.donationInterface.errorLog';
		}
		if ( $this->gateway->showMonthlyConvert() ) {
			$resources[] = 'ext.donationInterface.monthlyConvert';
		}
		return $resources;
	}

	public function getStyleModules() {
		$modules = [ 'ext.donationInterface.mustache.styles' ];
		$gatewayModules = $this->gateway->getConfig( 'ui_modules' );
		$this->addModules( 'styles', $modules, $gatewayModules );
		return $modules;
	}

	protected function addModules( $key, &$modules, $newModules ) {
		if ( !empty( $newModules[$key] ) ) {
			$modules = array_merge(
				$modules,
				(array)$newModules[$key]
			);
		}
	}

	protected function getTopLevelTemplate() {
		return $this->gateway->getGlobal( 'Template' );
	}

	protected function getImagePath( $name ) {
		return "{$this->scriptPath}/extensions/DonationInterface/gateway_forms/includes/{$name}";
	}

	protected function getPartials( array $data ) {
		$partials = [];
		if ( empty( $data['variant'] ) ) {
			$variantDir = false;
		} else {
			$variantDir = $this->gateway->getGlobal( 'VariantConfigurationDirectory' ) .
				DIRECTORY_SEPARATOR . $data['variant'] . DIRECTORY_SEPARATOR;
		}
		foreach ( self::$partials as $partial ) {
			$filename = $partial . self::EXTENSION;
			if (
				$variantDir &&
				file_exists( $variantDir . $filename )
			) {
				$partials[$partial] = rtrim( file_get_contents(
					$variantDir . $filename
				), "\r\n" );
			} else {
				$partials[$partial] = rtrim( file_get_contents(
					self::$baseDir . DIRECTORY_SEPARATOR . $filename
				), "\r\n" );
			}
		}
		return $partials;
	}
}
