<?php

use MediaWiki\MediaWikiServices;

/**
 * GatewayChooser acts as a gateway-agnostic landing page.
 * When passed a country, currency, and payment method combination, it determines the
 * appropriate gateway based on gateway configurations and priority rules.
 *
 * @author Damilare Adedoyin <dadedoyin@wikimedia.org>
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 * @author Wenjun Fan <wfan@wikimedia.org>
 * @author Peter Gehres <pgehres@wikimedia.org>
 * @author Jack Gleeson <jgleeson@wikimedia.org>
 * @author Andrew Green <agreen@wikimedia.org>
 * @author Katie Horn <khorn@wikimedia.org>
 * @author Christine Stone <cstone@wikimedia.org>
 * @author Matt Walker <mwalker@wikimedia.org>
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
		// Bow out if gateway chooser is not enabled
		if ( !$this->getConfig()->get( 'DonationInterfaceEnableGatewayChooser' ) ) {
			throw new BadTitleError();
		}

		// Also bow out if we're in maintenance mode
		if ( $this->getConfig()->get( 'DonationInterfaceFundraiserMaintenance' ) ) {
			$this->getOutput()->redirect( Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL(), '302' );
			return;
		}

		// Get an associative array of params from the URL. The ones we look at to determine
		// gateway options will be sanitized/vaidated, and the rest will be fetched verbatim.
		$params = $this->getParamsFromURL();

		// Find possible gateways
		$supportedGateways = $this->getSupportedGateways(
			$params[ 'country' ],
			$params[ 'currency' ],
			$params[ 'payment_method' ],
			$params[ 'payment_submethod' ],
			$params[ 'recurring' ],
			$params[ 'variant' ]
		);

		// If there are no supported gateways for these inputs, log an error and show an
		// error page.
		if ( count( $supportedGateways ) === 0 ) {
			$this->logger->error(
				'No supported gateway for parameters: ' . print_r( $params, true ) .
				' from referrer ' . $this->getRequest()->getHeader( 'referer' ) );

			$this->getOutput()->showErrorPage(
				'donate_interface-error-msg-general',
				'donate_interface-error-no-form',
				[ $this->getConfig()->get( 'DonationInterfaceProblemsEmail' ) ]
			);

			return;
		}

		// If a specific gateway was requested and it's supported, choose it
		if ( $params[ 'gateway' ] && in_array( $params[ 'gateway' ], $supportedGateways ) ) {
			$selectedGateway = $params[ 'gateway' ];

		} elseif ( count( $supportedGateways ) === 1 ) {
			// If only one gateway is supported, choose it
			$selectedGateway = $supportedGateways[ 0 ];

		} else {
			// We need to choose from among two or more supported gateways
			$selectedGateway = $this->chooseGatewayByPriority( $supportedGateways, $params );
		}

		// Get the URL and perform the redirection
		$redirectURL = self::buildGatewayPageURL( $selectedGateway, $params, $this->getConfig() );
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Build a URL to a payments form with the data that we have.
	 *
	 * @param string $gateway The short name of the payment gateway.
	 * @param array $params An array of params to send to the gateway page.
	 * @param ?Config $mwConfig MediaWiki Config
	 *
	 * @return string URL of special page for $gateway with $params on the querystring.
	 */
	public static function buildGatewayPageURL( string $gateway, array $params,
		?Config $mwConfig = null ) {
		// Get an instance of Mediawiki Config if one was not provided
		if ( !$mwConfig ) {
			$mwConfig = MediaWikiServices::getInstance()->getMainConfig();
		}

		// Remove empty values
		$params = array_filter( $params );

		// Add an appeal parameter if none was present
		$params = array_merge( [
			'appeal' => $mwConfig->get( 'DonationInterfaceDefaultAppeal' ),
		], $params );

		$specialPage = GatewayPage::getGatewayPageName( $gateway );
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
	 * Get all the gateways supported for the provided imputs.
	 *
	 * @param string $country
	 * @param string|null $currency
	 * @param string $paymentMethod
	 * @param string|null $paymentSubmethod
	 * @param bool $recurring
	 * @param string|null $variant
	 * @return array
	 */
	private function getSupportedGateways(
		string $country,
		?string $currency,
		string $paymentMethod,
		?string $paymentSubmethod,
		bool $recurring,
		?string $variant
	): array {
		$possbleGateways = [];
		$mwConfig = $this->getConfig(); // Main MediaWiki config object, via superclass
		$enabledGateways = GatewayAdapter::getEnabledGateways( $mwConfig );

		// Loop over enabled gateways to find ones supported for these inputs
		foreach ( $enabledGateways as $enabledGateway ) {
			$gatewayConfig =
				ConfigurationReader::createForGateway( $enabledGateway, $variant, $mwConfig )
				->readConfiguration();

			// TODO Knowledge about configuration layout should be encapsulated somewhere
			// See https://phabricator.wikimedia.org/T291699

			// Check availability for country; config is a flat array, and
			// $country input and countries config are always expected.
			if ( !in_array( $country, $gatewayConfig[ 'countries' ] ) ) {
				continue;
			}

			// Check availability for currency; config is a flat array
			// currencies config is always expected.
			if ( $currency && !in_array( $currency, $gatewayConfig[ 'currencies' ] ) ) {
				continue;
			}

			// Check availability for payment method, and, if requested, recurring;
			// in config, payment methods codes are keys of the outer array, though
			// payment_methods.yaml can also be empty.
			// $paymentMethod input is always expected.
			if ( !empty( $gatewayConfig[ 'payment_methods' ] ) ) {

				$supportedPaymentMethods = $gatewayConfig[ 'payment_methods' ];
				if ( !isset( $supportedPaymentMethods[ $paymentMethod ] ) ) {
					continue;
				}

				// Recurring availability for the payment method is indicated by a key
				// on the associative array that is the value for the payment method
				if ( $recurring &&
					empty( $supportedPaymentMethods[ $paymentMethod ][ 'recurring' ] ) ) {
					continue;
				}
			}

			// Check availability of requested payment submethod in this country
			if (
				$paymentSubmethod
				&& !empty( $gatewayConfig[ 'payment_submethods' ] )
				&& !empty( $gatewayConfig[ 'payment_submethods' ][ 'countries' ] )
				&& empty( $gatewayConfig[ 'payment_submethods' ][ 'countries' ][ $country ] )
			) {
				continue;
			}

			$possbleGateways[] = $enabledGateway;
		}

		return $possbleGateways;
	}

	/**
	 * @deprecated
	 *
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
	 * @deprecated Should be removed with once ffname is gone
	 *
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

	/**
	 * @deprecated Should be removed with once ffname is gone
	 */
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
	 * In here we're gonna check a predefined list of
	 * priority rules to see which of the supported gateways
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
					'none of supported gateways (' . implode( ',', $supportedGateways ) . '), ' .
					' from referrer ' . $this->getRequest()->getHeader( 'referer' )
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
				'No rules matched parameters ' . print_r( $params, true ) .
				' from referrer ' . $this->getRequest()->getHeader( 'referer' ) . '; arbitrarily ' .
				'choosing from supported gateways (' . implode( ',', $supportedGateways ) . '). ' .
				'Consider adding a default rule (one with no conditions) to the end of ' .
				'$wgDonationInterfaceGatewayPriorityRules'
			);
			return $supportedGateways[0];
		}
		// No gateways were supported in the first place - return null and trigger an error page
		return null;
	}

	/**
	 * Get params from the URL, sanitizing and, in some cases, validating the ones we
	 * use to get possible gateway, and including the rest verbatim.
	 *
	 * @return array associative array of params from the URL
	 */
	private function getParamsFromURL(): array {
		// Get country code from request param, or, if it's not sent or is invalid, use
		// geoip lookup
		$country = $this->sanitizedValOrNull( 'country' );

		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			$country = CountryValidation::lookUpCountry( $this->getRequest()->getIP() );

			if ( !$country || !CountryValidation::isValidIsoCode( $country ) ) {
				$this->logger->warning(
					"GeoIP lookup returned invalid country '$country'!, " .
					'from referrer ' . $this->getRequest()->getHeader( 'referer' ) );
			}
		}

		// Get currency from request param. Also check for a value from the currency_code
		// param, and if there is one, warn so we can track and remove these
		$currency = $this->sanitizedValOrNull( 'currency' );

		if ( !$currency ) {
			$currency = $this->sanitizedValOrNull( 'currency_code' );
			if ( $currency ) {
				$this->logger->warning(
					'Deprecated currency_code param from referrer ' .
					$this->getRequest()->getHeader( 'referer' ) );
			}
		}

		$paymentMethod = $this->sanitizedValOrNull( 'payment_method' );
		// No payment method will cause an error a little further down
		if ( !$paymentMethod ) {
			$this->logger->warning(
				'No payment method URL param from referrer ' .
				$this->getRequest()->getHeader( 'referer' ) );
		}

		// For recurring, we'll interpret no URL param, 'false', '0' and '' as false.
		// This follows legacy behavior, to ensure existing links work as expected.

		// sanitizedValOrNull() will return null if the param is absent, '' or '0'
		$recurringRawVal = $this->sanitizedValOrNull( 'recurring' );
		if ( $recurringRawVal === 'false' ) {
			$recurring = false;
		} else {
			// map null to explicitly false
			$recurring = (bool)$recurringRawVal;
		}

		// These are the parameters that are actually used to find possible gateways
		$params = [
			'country' => $country,
			'currency' => $currency,
			'payment_method' => $paymentMethod,
			'payment_submethod' => $this->sanitizedValOrNull( 'payment_submethod' ),
			'recurring' => $recurring,
			'gateway' => $this->sanitizedValOrNull( 'gateway' ),
			'variant' => $this->sanitizedValOrNull( 'variant' )
		];

		// All other URL parameters (except title and deprecated currency_code) will be
		// passed through on the redirect URL without sanitization or validation
		$passThruParams = [];
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			if ( !in_array( $key, $params ) && $key !== 'title' && $key !== 'currency_code' ) {
				$passThruParams[ $key ] = $value;
			}
		}

		return $params + $passThruParams;
	}

	/**
	 * Get the sanitized string value of a URL parameter. If the parameter was not present
	 * or is an empty string (or, more precisely, is empty()), return null.
	 *
	 * @param string $paramName
	 * @return ?string
	 */
	private function sanitizedValOrNull( string $paramName ): ?string {
		$val = $this->getRequest()->getVal( $paramName, null );

		if ( empty( $val ) ) {
			return null;
		}

		$sanitizedVal = preg_replace( "/[^A-Za-z0-9_\-]+/", "", $val );

		if ( $sanitizedVal !== $val ) {
			$this->logger->warning(
				"Unexpected characters in $paramName; sanitizd value is $sanitizedVal, " .
				'from referrer ' . $this->getRequest()->getHeader( 'referer' )
			);
		}

		return $sanitizedVal;
	}
}
