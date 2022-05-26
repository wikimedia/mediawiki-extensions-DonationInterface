<?php
/**
 * GatewayChooser acts as a gateway-agnostic landing page for second-step forms.
 * When passed a country, currency, and payment method combination, it determines the
 * appropriate form based on the forms defined for that combination taking into account
 * the currently available payment processors.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 * @author Matt Walker <mwalker@wikimedia.org>
 * @author Katie Horn <khorn@wikimedia.org>
 */
class GatewayChooser extends UnlistedSpecialPage {

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter', 'GatewayChooser' );
		parent::__construct( 'GatewayChooser' );
	}

	public function execute( $par ) {
		if ( !$this->getConfig()->get( 'DonationInterfaceEnableGatewayChooser' ) ) {
			throw new BadTitleError();
		}

		if ( $this->getConfig()->get( 'DonationInterfaceFundraiserMaintenance' ) ) {
			$this->getOutput()->redirect( Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL(), '302' );
			return;
		}

		$request = $this->getRequest();
		// Get a query string parameter or null if blank
		$getValOrNull = static function ( $paramName ) use ( $request ) {
			$val = $request->getVal( $paramName, null );
			if ( $val === '' ) {
				$val = null;
			}
			return $val;
		};

		$country = $getValOrNull( 'country' );

		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			// Lookup the country
			$ip = $this->getRequest()->getIP();
			$country = CountryValidation::lookUpCountry( $ip );
			if ( $country && !CountryValidation::isValidIsoCode( $country ) ) {
				$this->logger->warning(
					"GeoIP lookup returned bogus code '$country'! No country available."
				);
			}
		}

		$currency = $getValOrNull( 'currency' );
		// TODO: remove when incoming links are updated
		if ( !$currency ) {
			$currency = $getValOrNull( 'currency_code' );
		}
		$paymentMethod = $getValOrNull( 'payment_method' );
		$paymentSubMethod = $getValOrNull( 'payment_submethod' );
		$gateway = $getValOrNull( 'gateway' );
		$recurring = $this->getRequest()->getVal( 'recurring', false );

		// FIXME: here we should check for ffname, and if that's a valid form skip the choosing
		$form = self::getOneValidForm( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, $gateway );

		// If we can't find a good form and we're forcing a gateway, try again without the gateway
		if ( $form === null && $gateway ) {
			$form = self::getOneValidForm( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, null );
		}

		if ( $form === null ) {
			$utmSource = $this->getRequest()->getVal( 'utm_source', '' );

			$this->logger->error(
				"Not able to find a valid form for country '$country', currency '$currency', " .
				"method '$paymentMethod', submethod '$paymentSubMethod', " .
				"recurring: '$recurring', gateway '$gateway' for utm source '$utmSource'"
			);
			$this->getOutput()->showErrorPage(
				'donate_interface-error-msg-general',
				'donate_interface-error-no-form',
				[ GatewayAdapter::getGlobal( 'ProblemsEmail' ) ]
			);
			return;
		}

		$params = [
			'recurring' => $recurring,
		];

		// Pass any other params that are set. We do not skip ffname or form_name because
		// we wish to retain the query string override.
		$excludeKeys = [ 'title', 'recurring' ];
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			// Skip the required variables
			if ( !in_array( $key, $excludeKeys ) ) {
				$params[$key] = $value;
			}
		}

		// TODO: remove when incoming links are updated
		if ( !empty( $params['currency_code'] ) ) {
			if ( empty( $params['currency'] ) ) {
				$params['currency'] = $params['currency_code'];
			}
			unset( $params['currency_code'] );
		}

		$formDef = self::getFormDefinition( $form );
		$redirectURL = $this->buildGatewayPageUrl( $formDef['gateway'], $params, $this->getConfig() );

		// Perform the redirection
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Build a URL to a payments form with the data that we have.
	 *
	 * @param string $gateway The short name of the payment gateway.
	 * @param array $params An array of params to send to the gateway page.
	 * @param Config $mwConfig MediaWiki Config
	 *
	 * @return string URL of special page for $gateway with $params on the querystring.
	 */
	public static function buildGatewayPageUrl( string $gateway, array $params, Config $mwConfig ) {
		$gatewayClasses = $mwConfig->get( 'DonationInterfaceGatewayAdapters' );

		$params = array_merge( [
			'appeal' => $mwConfig->get( 'DonationInterfaceDefaultAppeal' ),
		], $params );

		// The special page name is the gateway adapter class name with 'Adapter'
		// replaced with 'Gateway'.
		$specialPage = str_replace(
			'Adapter',
			'Gateway',
			$gatewayClasses[$gateway]
		);
		return self::getTitleFor( $specialPage )->getLocalURL( $params );
	}

	/**
	 * @deprecated Use buildRedirectUrl instead
	 * TODO: delete once we refactor error forms.
	 *
	 * Build a URL to a payments form, with the data that we have.
	 * If we have supplied no form_key, it will build a URL to the form
	 * chooser itself, so we can get a new one that satisfies the
	 * requirements specified in $other_params
	 * $other_params will override everything except $form_key (ffname)
	 * @param string $form_key The ffname we would like to go back to. In
	 * the event that none is supplied, you'll go back to the Form Chooser
	 * to get one.
	 * @param array $other_params An array of any params that DonationData
	 * will harvest and understand.
	 * @throws MWException on ambiguous gateway or bad gateway name
	 * @return string The form URL
	 */
	public static function buildPaymentsFormURL( $form_key, $other_params = [] ) {
		global $wgDonationInterfaceDefaultAppeal,
			$wgDonationInterfaceGatewayAdapters;

		// And... construct the URL
		$params = [
			'appeal' => $wgDonationInterfaceDefaultAppeal,
			'ffname' => $form_key,
		];

		if ( array_key_exists( 'ffname', $other_params ) ) {
			unset( $other_params['ffname'] );
		}

		$params = array_merge( $params, $other_params );

		$rechoose = false;
		if ( !strlen( $form_key ) ) {
			// send them to the form chooser itself.
			$rechoose = true;
		}

		$specialpage = '';
		if ( $rechoose ) {
			$specialpage = 'GatewayChooser';
		} else {
			$form_info = self::getFormDefinition( $form_key );

			// support for multi-gateway forms, and error forms
			$gateway = $form_info['gateway'];

			if ( is_array( $gateway ) ) {
				// Accept gateway hint if it's already specified for this form.
				if ( array_key_exists( 'gateway', $params ) && in_array( $params['gateway'], $gateway ) ) {
					$gateway = $params['gateway'];
				} else {
					// TODO: Throw an UnexpectedValueException, once we've updated payments mw-core.
					throw new MWException( __FUNCTION__ . " Cannot determine appropriate gateway to use for ffname '$form_key'. " );
				}
			}

			// The special page name is the gateway adapter name but with
			// 'Adapter' replaced with 'Gateway'
			$specialpage = str_replace(
				'Adapter',
				'Gateway',
				$wgDonationInterfaceGatewayAdapters[$gateway]
			);
		}

		// set the default redirect
		return self::getTitleFor( $specialpage )->getLocalURL( $params );
	}

	/**
	 * Gets all the valid forms that match the provided parameters.
	 * These parameters should exactly match the params in getOneValidForm.
	 * TODO: Should be passed as a hash or object.
	 * $wgDonationInterfaceAllowedHtmlForms Contains all enabled forms and meta data
	 * @param string|null $country Optional country code filter
	 * @param string|null $currency Optional currency code filter
	 * @param string|null $payment_method Optional payment method filter
	 * @param string|null $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param bool $recurring Whether or not we should return recurring forms. Default = false.
	 * @param string|null $gateway Optional gateway to force.
	 * @return array
	 */
	protected static function getAllValidForms( $country = null, $currency = null, $payment_method = null,
		$payment_submethod = null, $recurring = false, $gateway = null
	) {
		global $wgDonationInterfaceAllowedHtmlForms;
		$forms = $wgDonationInterfaceAllowedHtmlForms;

		// Destroy all optional params that have no values and should be null.
		$optionals = [
			'country',
			'currency',
			'payment_method',
			'payment_submethod',
			'gateway'
		];

		foreach ( $optionals as $var ) {
			if ( $$var === '' ) {
				$$var = null;
			}
		}

		// First get all the valid and enabled gateways capable of processing shtuff
		$valid_gateways = self::getAllEnabledGateways();
		if ( $gateway !== null ) {
			// If the requested gateway is valid and enabled, only allow
			// forms for that gateway. Otherwise try 'em all.
			if ( in_array( $gateway, $valid_gateways ) ) {
				$valid_gateways = [ $gateway ];
			}
		}

		// then remove the forms that we don't want.
		foreach ( $forms as $name => &$meta ) {
			// Prefilter for sillyness
			// filter out all special forms (like error pages)
			if ( array_key_exists( 'special_type', $meta ) ) {
				unset( $forms[$name] );
				continue;
			}

			foreach ( [ 'gateway', 'payment_methods' ] as $paramName ) {
				if ( !array_key_exists( $paramName, $meta ) ) {
					unset( $forms[$name] );
					continue 2;
				}
			}
			foreach ( [ 'countries', 'currencies' ] as $paramName ) {
				if ( !array_key_exists( $paramName, $meta ) ) {
					$meta[$paramName] = 'ALL';
				}
			}

			// filter on enabled gateways
			if ( !DataValidator::value_appears_in( $meta['gateway'], $valid_gateways ) ) {
				unset( $forms[$name] );
				continue;
			}

			// filter on country
			if ( $country !== null && !DataValidator::value_appears_in( $country, $meta['countries'] ) ) {
				unset( $forms[$name] );
				continue;
			}

			if ( $currency !== null && !DataValidator::value_appears_in( $currency, $meta['currencies'] )
			) {
				unset( $forms[$name] );
				continue;
			}

			// filter on payment method
			if ( $payment_method !== null ) {
				if ( !DataValidator::value_appears_in( $payment_method, array_keys( $meta['payment_methods'] ) ) ) {
					// Well, the root payment method is invalid, so... die!
					unset( $forms[$name] );
					continue;
				}

				// filter on payment submethod
				// CURSES! I didn't want this to be buried down in here, but I guess it's sort of reasonable. Ish.
				if (
					$payment_submethod !== null &&
					!DataValidator::value_appears_in( $payment_submethod, $meta['payment_methods'][$payment_method] )
				) {
					unset( $forms[$name] );
					continue;
				}
			}

			// NOOOOES.
			// ...but actually yes.
			if ( $recurring === 'false' || $recurring === '0' ) {
				$recurring = false;
			}

			// filter on recurring
			if ( DataValidator::value_appears_in( 'recurring', $meta ) !== (bool)$recurring ) {
				unset( $forms[$name] );
				continue;
			}
		}
		return $forms;
	}

	/**
	 * Gets one valid forms that match the provided parameters.
	 * These parameters should exactly match the params in getAllValidForms.
	 * @param string|null $country Optional country code filter
	 * @param string|null $currency Optional currency code filter
	 * @param string|null $payment_method Optional payment method filter
	 * @param string|null $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param bool $recurring Whether or not we should return recurring forms. Default = false.
	 * @param string|null $gateway Optional gateway to force.
	 * @return array
	 */
	public static function getOneValidForm( $country = null, $currency = null, $payment_method = null, $payment_submethod = null, $recurring = false, $gateway = null
	) {
		$forms = self::getAllValidForms( $country, $currency, $payment_method, $payment_submethod, $recurring, $gateway );
		$form = self::pickOneForm( $forms, $currency, $country, $payment_method );

		// TODO:
		// This here, would be an excellent place to default to
		// "sorry, we don't support that thing you're trying to do."

		return $form;
	}

	/**
	 * Gets the array of settings and capability definitions for the form
	 * specified in $form_key.
	 * $wgDonationInterfaceAllowedHtmlForms is the global array
	 * of enabled forms.
	 * @param string $form_key The name of the form (ffname) we're looking
	 * for. Should map to a first-level key in
	 * $wgDonationInterfaceAllowedHtmlForms.
	 * @return array|bool The settings and capability definitions for
	 * that form in array format, or false if it isn't a valid and enabled
	 * form.
	 */
	protected static function getFormDefinition( $form_key ) {
		global $wgDonationInterfaceAllowedHtmlForms;
		if ( array_key_exists( $form_key, $wgDonationInterfaceAllowedHtmlForms ) ) {
			return $wgDonationInterfaceAllowedHtmlForms[$form_key];
		} else {
			return false;
		}
	}

	/**
	 * Checks to see if the ffname supplied is a valid form matching
	 * the donor's country and payment preferences.
	 *
	 * @param string $ffname The form name to check
	 * @param array $prefs country and payment preferences, including:
	 *  country Optional country code filter
	 *  currency Optional currency code filter
	 *  payment_method Optional payment method filter
	 *  payment_submethod Optional payment submethod filter
	 *   THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD
	 *  recurring Whether we should return recurring forms (default false)
	 *  gateway Optional gateway to force
	 * @return bool True if the named form matches the requirements
	 */
	public static function isValidForm( $ffname, $prefs = [] ) {
		$form = self::getFormDefinition( $ffname );
		if ( !$form ) {
			return false;
		}

		// First make sure these match if present
		$keyMap = [
			'country' => 'countries',
			'gateway' => 'gateway',
			'currency' => 'currencies',
		];
		foreach ( $keyMap as $prefKey => $formKey ) {
			if (
				!self::prefAllowedBySpec( $prefs, $prefKey, $form, $formKey )
			) {
				return false;
			}
		}

		// Filter by method if supplied
		if (
			!empty( $prefs['payment_method'] ) &&
			!empty( $form['payment_methods'] )
		) {
			$requestedMethod = $prefs['payment_method'];
			$formMethods = $form['payment_methods'];
			if ( !array_key_exists( $requestedMethod, $formMethods ) ) {
				return false;
			}
			// Filter by submethod if we have enough info
			if ( !empty( $prefs['payment_submethod'] ) ) {
				$formSubmethods = $formMethods[$requestedMethod];
				$submethod = $prefs['payment_submethod'];
				if (
				!DataValidator::value_appears_in( $submethod, $formSubmethods )
				) {
					return false;
				}
			}
		}

		// Any special form is valid if it got past those checks
		if ( array_key_exists( 'special_type', $form ) ) {
			return true;
		}

		// Make sure the form supports recurring if requested
		$formIsRecurring = in_array( 'recurring', $form );
		$wantRecurring = (
			!empty( $prefs['recurring'] ) &&
			$prefs['recurring'] !== '0' &&
			$prefs['recurring'] !== 'false'
		);
		if ( $wantRecurring != $formIsRecurring ) {
			return false;
		}

		return true;
	}

	protected static function prefAllowedBySpec(
		$prefs, $prefKey, $form, $formKey
	) {
		// we only filter on keys that exist
		if ( empty( $prefs[$prefKey] ) || empty( $form[$formKey] ) ) {
			return true;
		}
		$prefValue = $prefs[$prefKey];
		$formSetting = $form[$formKey];

		return DataValidator::value_appears_in( $prefValue, $formSetting );
	}

	/**
	 * Return an array of all the currently enabled gateways.
	 *
	 * @return array of gateway identifiers.
	 */
	protected static function getAllEnabledGateways() {
		global $wgDonationInterfaceGatewayAdapters;

		$enabledGateways = [];
		foreach ( $wgDonationInterfaceGatewayAdapters as $identifier => $gatewayClass ) {
			if ( $gatewayClass::getGlobal( 'Enabled' ) ) {
				$enabledGateways[] = $identifier;
			}
		}
		return $enabledGateways;
	}

	/**
	 * In the event that we have more than one valid form, we need to figure out
	 * which one we ought to be using.
	 * In the absence of any data regarding form preferences, we should pick one
	 * that appears to be localized for whatever we requested.
	 * If we still have more than one, take the one with the most payment
	 * submethods.
	 * If we *still* have more than one, just... take the top or something.
	 * @param array $valid_forms All the forms that are valid for the parameters
	 * we've used.
	 * @param string $currency
	 * @param string $country
	 * @param string $payment_method
	 * @return mixed
	 */
	protected static function pickOneForm( $valid_forms, $currency, $country, $payment_method ) {
		if ( count( $valid_forms ) === 1 ) {
			reset( $valid_forms );
			return key( $valid_forms );
		}

		// We know there are multiple options for the donor at this point.
		// selection_weight = 0 is interpreted as meaning "don't pick this
		// form unless we asked for it by name", so remove those forms before
		// we apply any other criteria.
		// FIXME: once other FIXMEs are complete, use a more explicit settings
		// key like 'onlyOnRequest' => true and filter in getAllValidForms
		$zeroWeightForms = [];
		foreach ( $valid_forms as $form_name => $meta ) {
			if (
				isset( $meta['selection_weight'] ) &&
				$meta['selection_weight'] === 0
			) {
				$zeroWeightForms[] = $form_name;
			}
		}
		// If all the valid forms are zero-weighted at this point,
		// we're probably specifying gateway. If only some valid forms
		// are zero-weighted, remove those from consideration.
		if ( count( $zeroWeightForms ) < count( $valid_forms ) ) {
			foreach ( $zeroWeightForms as $failform ) {
				unset( $valid_forms[$failform] );
			}
		}
		// general idea: If one form has constraints for the following ordered
		// keys, and some forms do not have that constraint, prefer the one with
		// the explicit constraints.
		// But, it naturally got more complicated when I started considering the
		// ivnerse.
		$keys = [
			'currencies' => $currency,
			'countries' => $country,
		];
		foreach ( $keys as $key => $look ) {
		// got to loop on keys first, as valid_forms loop will hopefully shrink as we're going.
			$failforms = [];
			foreach ( $valid_forms as $form_name => $meta ) {
				if ( ( $look !== null && !array_key_exists( $key, $meta ) )
					|| $look === null && array_key_exists( $key, $meta ) ) {
					$failforms[] = $form_name;
				}
			}
			if ( !empty( $failforms ) && count( $failforms ) != count( $valid_forms ) ) {
				// Kill everybody who didn't have it, because somebody totally did.
				foreach ( $failforms as $failform ) {
					unset( $valid_forms[$failform] );
				}
			}
			if ( count( $valid_forms ) === 1 ) {
				reset( $valid_forms );
				return key( $valid_forms );
			}
		}

		// now, go for the one with the most explicitly defined payment submethods.
		$submethod_counter = [];
		foreach ( $valid_forms as $form_name => $meta ) {
			if ( array_key_exists( $payment_method, $meta['payment_methods'] ) ) {
				$submethods = $meta['payment_methods'][$payment_method];
				if ( is_array( $submethods ) ) {
					$submethod_counter[$form_name] = count( $submethods );
				} elseif ( !empty( $submethods ) ) {
					$submethod_counter[$form_name] = 1;
				}
			} else {
				$submethod_counter[$form_name] = 0;
			}
		}
		arsort( $submethod_counter, SORT_NUMERIC );
		$max = 0;
		foreach ( $submethod_counter as $form_name => $count ) {
			if ( $count > $max ) {
				$max = $count; // after the arsort, this will happen the first time and that's it.
			}
			if ( $count < $max ) {
				unset( $valid_forms[$form_name] );
			}
		}

		if ( count( $valid_forms ) === 1 ) {
			reset( $valid_forms );
			return key( $valid_forms );
		}

		// Choose the form with the highest selection weight.
		$greatest_weight = 0;
		$heaviest_form = null;
		foreach ( $valid_forms as $form_name => &$meta ) {
			// Assume a default weight of 100.
			if ( !array_key_exists( 'selection_weight', $meta ) ) {
				$meta['selection_weight'] = 100;
			}

			// Note that we'll never choose a weightless form.
			if ( $meta['selection_weight'] > $greatest_weight ) {
				$heaviest_form = $form_name;
				$greatest_weight = $meta['selection_weight'];
			}
		}

		return $heaviest_form;
	}

	/**
	 * In here we're gonna check a predefined list of
	 * priority rules to see which of the available gateways
	 * best fits the user parameters.
	 *
	 * Example rules would look like:
	 * $rules = [
	 *    [
	 * 	    'conditions' => [ 'utm_medium' => 'endowment' ],
	 * 	    'gateways' => [ 'ingenico', 'paypal_ec' ]
	 * 	  ],
	 *    [
	 *      'conditions' => [
	 *        'payment_method' => 'cc',
	 *        'country' => [ 'NL', 'IL', 'FR' ]
	 *      ],
	 *      'gateways' => [ 'adyen', 'ingenico' ]
	 *    ],
	 *    [
	 * 	    # No conditions, this is treated as default.
	 * 		# Should be last in the list as it will always match.
	 * 	    'gateways' => [ 'ingenico', 'adyen', 'paypal_ec', 'amazon', 'astropay' ]
	 * 	  ]
	 * ];
	 *
	 * @param array $supportedGateways List of gateway codes assumed to
	 *  support the requested country / currency / payment_method
	 * @param array $params Query-string parameters
	 * @return string|null Selected gateway code
	 */
	public function chooseGatewayByPriority( $supportedGateways, $params ) {
		$rules = $this->getConfig()->get( 'DonationInterfaceGatewayPriorityRules' );

		foreach ( $rules as $rule ) {
			// Do our $params match all the conditions for this rule?
			// A rule with no conditions will always be matched.
			$ruleMatches = true;
			if ( isset( $rule['conditions'] ) ) {
				// Loop over all the conditions looking for any that don't match
				foreach ( $rule['conditions'] as $conditionName => $conditionValue ) {
					// If the key of a condition is not in the params, the rule does not match
					if ( !isset( $params[$conditionName] ) ) {
						$ruleMatches = false;
						break;
					}
					// Condition value is a list, e.g. of countries
					if ( is_array( $conditionValue ) ) {
						if ( in_array( $params[$conditionName], $conditionValue ) ) {
							continue;
						} else {
							$ruleMatches = false;
							break;
						}
					}
					// Condition value is a scalar, just check it against the param value
					if ( $params[$conditionName] == $conditionValue ) {
						continue;
					} else {
						$ruleMatches = false;
						break;
					}
				}
			}
			if ( $ruleMatches ) {
				// Find the first in the rule's gateways list which is in $supportedGateways
				foreach ( $rule['gateways'] as $ruleGateway ) {
					if ( in_array( $ruleGateway, $supportedGateways ) ) {
						return $ruleGateway;
					}
				}
				// Complain, this is fishy. If for example a rule states that all endowment donations
				// should go to gateways X and Y, and we get to this point, it means an endowment
				// donation has come in for a method or country not supported by gateways X or Y.
				$conditionMessage = isset( $rule['conditions'] ) ?
					'rule with conditions ' . print_r( $rule['conditions'], true ) :
					'default rule';
				$this->logger->warning(
					'Matched ' . $conditionMessage . ' ' .
					'and parameters ' . print_r( $params, true ) . ', but rule gateway list includes ' .
					'none of supported gateways (' . implode( ',', $supportedGateways ) . ').'
				);
			}
		}

		// We only had one supported gateway, but no rules matched or the matching rule didn't include
		// the supported gateway. Dealing with this here rather than at top of method, so that we hit
		// the code to log a warning if a matched rule points to an unsupported gateway.
		if ( count( $supportedGateways ) === 1 ) {
			return $supportedGateways[0];
		}
		// Multiple gateways supported, but no rule matched. Warn and return the first supported gateway.
		if ( count( $supportedGateways ) > 1 ) {
			$this->logger->warning(
				'No rules matched parameters ' . print_r( $params, true ) . '; arbitrarily ' .
				'choosing from supported gateways (' . implode( ',', $supportedGateways ) . '). ' .
				'Consider adding a default rule (one with no conditions) to the end of ' .
				'$wgDonationInterfaceGatewayPriorityRules'
			);
			return $supportedGateways[0];
		}
		// No gateways were supported in the first place - return null and trigger an error page
		return null;
	}
}
