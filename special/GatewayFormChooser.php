<?php
/*
 * GatewayFormChooser acts as a gateway-agnostic landing page for second-step forms.
 * When passed a country, currency, and payment method combination, it determines the
 * appropriate form based on the forms defined for that combination taking into account
 * the currently available payment processors.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 * @author Matt Walker <mwalker@wikimedia.org>
 * @author Katie Horn <khorn@wikimedia.org>
 */
class GatewayFormChooser extends UnlistedSpecialPage {

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter', 'FormChooser' );
		parent::__construct( 'GatewayFormChooser' );
	}

	function execute( $par ) {
		global $wgContributionTrackingFundraiserMaintenance,
			$wgContributionTrackingFundraiserMaintenanceUnsched,
			$wgDonationInterfaceEnableFormChooser;

		if ( !$wgDonationInterfaceEnableFormChooser ) {
			throw new BadTitleError();
		}


		if( $wgContributionTrackingFundraiserMaintenance
			|| $wgContributionTrackingFundraiserMaintenanceUnsched ){
			$this->getOutput()->redirect( Title::newFromText('Special:FundraiserMaintenance')->getFullURL(), '302' );
			return;
		}

		$request = $this->getRequest();
		// Get a query string parameter or null if blank
		$getValOrNull = function( $paramName ) use ( $request ) {
			$val = $request->getVal( $paramName, null );
			if ( $val === '' ) {
				$val = null;
			}
			return $val;
		};

		$country = $getValOrNull( 'country' );
		$currency = $getValOrNull( 'currency_code' );
		$paymentMethod = $getValOrNull( 'payment_method' );
		$paymentSubMethod = $getValOrNull( 'payment_submethod' );
		$gateway = $getValOrNull( 'gateway' );
		$recurring = $this->getRequest()->getVal( 'recurring', false );

		// FIXME: This is clearly going to go away before we deploy this bizniss.
		$testNewGetAll = $this->getRequest()->getVal( 'testGetAll', false );
		if ( $testNewGetAll ){
			$forms = self::getAllValidForms( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, $gateway );
			echo "<pre>" . print_r( $forms, true ) . "</pre>";
			$form = self::pickOneForm( $forms, $currency, $country );
			echo "<pre>I choose you, " . print_r( $form, true) . "!</pre>";
			echo "<pre>Trying: " . ucfirst($forms[$form]['gateway']) . "Gateway</pre>";
			die();
		}

		$form = self::getOneValidForm( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, $gateway );

		if ( $form === null ) {
			$utmSource = $this->getRequest()->getVal( 'utm_source', '' );

			$this->logger->error(
				"Not able to find a valid form for country '$country', currency '$currency', method '$paymentMethod', submethod '$paymentSubMethod', recurring: '$recurring', gateway '$gateway' for utm source '$utmSource'"
			);
			$this->getOutput()->showErrorPage( 'donate_interface-error-msg-general', 'donate_interface-error-no-form' );
			return;
		}

		$params = array (
			'recurring' => $recurring,
		);

		// Pass any other params that are set. We do not skip ffname or form_name because
		// we wish to retain the query string override.
		$excludeKeys = array( 'title', 'recurring' );
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			// Skip the required variables
			if ( !in_array( $key, $excludeKeys ) ) {
				$params[$key] = $value;
			}
		}

		$redirectURL = self::buildPaymentsFormURL( $form, $params );

		// Perform the redirection
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
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
	 * @throw UnexpectedValueException
	 */
	static function buildPaymentsFormURL( $form_key, $other_params = array ( ) ) {
		// And... construct the URL
		$params = array (
			'appeal' => "JimmyQuote",
			'ffname' => $form_key,
		);

		if ( array_key_exists( 'ffname', $other_params ) ) {
			unset( $other_params['ffname'] );
		}

		$params = array_merge( $params, $other_params );

		$rechoose = false;
		if ( !strlen( $form_key ) ) {
			//send them to the form chooser itself.
			$rechoose = true;
		}

		$specialpage = '';
		if ( $rechoose ) {
			$specialpage = 'GatewayFormChooser';
		} else {
			$form_info = self::getFormDefinition( $form_key );

			if ( DataValidator::value_appears_in( 'redirect', $form_info ) ) {
				$params['redirect'] = '1';
			}

			//support for multi-gateway forms, and error forms
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

			// FIXME: We aren't doing ucfirst, more like camlcase.  Kludge like hell:
			switch ( $gateway ) {
				case 'globalcollect':
					$specialpage = 'GlobalCollectGateway';
					break;
				case 'worldpay':
					$specialpage = 'WorldpayGateway';
					break;
				default:
					$specialpage = ucfirst( $gateway ) . "Gateway";
			}
		}

		// set the default redirect
		return self::getTitleFor( $specialpage )->getLocalURL( $params );
	}

	/**
	 * Gets all the valid forms that match the provided paramters.
	 * These parameters should exactly match the params in getOneValidForm.
	 * TODO: Should be passed as a hash or object.
	 * @global array $wgDonationInterfaceAllowedHtmlForms Contains all whitelisted forms and meta data
	 * @param string $country Optional country code filter
	 * @param string $currency Optional currency code filter
	 * @param string $payment_method Optional payment method filter
	 * @param string $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param boolean $recurring Whether or not we should return recurring forms. Default = false.
	 * @param string $gateway Optional gateway to force.
	 * @return array
	 */
	static function getAllValidForms( $country = null, $currency = null, $payment_method = null,
		$payment_submethod = null, $recurring = false, $gateway = null
	) {
		global $wgDonationInterfaceAllowedHtmlForms;
		$forms = $wgDonationInterfaceAllowedHtmlForms;

		//Destroy all optional params that have no values and should be null.
		$optionals = array (
			'country',
			'currency',
			'payment_method',
			'payment_submethod',
			'gateway'
		);

		foreach ( $optionals as $var ) {
			if ( $$var === '' ) {
				$$var = null;
			}
		}

		// First get all the valid and enabled gateways capable of processing shtuff
		$valid_gateways = self::getAllEnabledGateways();
		if ( $gateway !== null ) {
			if ( in_array( $gateway, $valid_gateways ) ) {
				$valid_gateways = array( $gateway );
			} else {
				// Aaah; the requested gateway is not valid :'( Nothing to do but return nothing
				return array();
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

			foreach ( array( 'gateway', 'payment_methods' ) as $paramName ) {
				if ( !array_key_exists( $paramName, $meta ) ) {
					unset( $forms[$name] );
					continue 2;
				}
			}
			foreach ( array( 'countries', 'currencies' ) as $paramName ) {
				if ( !array_key_exists( $paramName, $meta ) ) {
					$meta[$paramName] = 'ALL';
				}
			}

			// filter on enabled gateways
			if ( !DataValidator::value_appears_in( $meta['gateway'], $valid_gateways ) ) {
				unset( $forms[$name] );
				continue;
			}

			//filter on country
			if ( !is_null( $country ) && !DataValidator::value_appears_in( $country, $meta['countries'] ) ) {
				unset( $forms[$name] );
				continue;
			}

			if ( !is_null( $currency ) && !DataValidator::value_appears_in( $currency, $meta['currencies'] )
			) {
				unset( $forms[$name] );
				continue;
			}

			//filter on payment method
			if ( !is_null( $payment_method ) ) {
				if ( !DataValidator::value_appears_in( $payment_method, array_keys( $meta['payment_methods'] ) ) ) {
					// Well, the root payment method is invalid, so... die!
					unset( $forms[$name] );
					continue;
				}
				
				//filter on payment submethod
				//CURSES! I didn't want this to be buried down in here, but I guess it's sort of reasonable. Ish.
				if (
					!is_null( $payment_submethod ) &&
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

			//filter on recurring
			if ( DataValidator::value_appears_in( 'recurring', $meta ) !== ( bool ) $recurring ) {
				unset( $forms[$name] );
				continue;
			}
		}
		return $forms;
	}

	/**
	 * Gets one valid forms that match the provided paramters.
	 * These parameters should exactly match the params in getAllValidForms.
	 * @param string $country Optional country code filter
	 * @param string $currency Optional currency code filter
	 * @param string $payment_method Optional payment method filter
	 * @param string $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param boolean $recurring Whether or not we should return recurring forms. Default = false.
	 * @param string $gateway Optional gateway to force.
	 * @return array
	 */
	static function getOneValidForm( $country = null, $currency = null, $payment_method = null, $payment_submethod = null, $recurring = false, $gateway = null
	) {
		$forms = self::getAllValidForms( $country, $currency, $payment_method, $payment_submethod, $recurring, $gateway );
		$form = self::pickOneForm( $forms, $currency, $country );

		//TODO:
		//This here, would be an excellent place to default to
		//"sorry, we don't support that thing you're trying to do."

		return $form;
	}

	/**
	 * Gets the array of settings and capability definitions for the form
	 * specified in $form_key.
	 * @global array $wgDonationInterfaceAllowedHtmlForms The global array
	 * of whitelisted (enabled) forms.
	 * @param string $form_key The name of the form (ffname) we're looking
	 * for. Should map to a first-level key in
	 * $wgDonationInterfaceAllowedHtmlForms.
	 * @return array|boolean The settings and capability definitions for
	 * that form in array format, or false if it isn't a valid and enabled
	 * form.
	 */
	static function getFormDefinition( $form_key ) {
		global $wgDonationInterfaceAllowedHtmlForms;
		if ( array_key_exists( $form_key, $wgDonationInterfaceAllowedHtmlForms ) ) {
			return $wgDonationInterfaceAllowedHtmlForms[$form_key];
		} else {
			return false;
		}
	}

	/**
	 * 
	 * @param string $form_key
	 * @return boolean
	 */
	static function isSupportedCountry( $country_iso, $form_key ) {
		static $countries = array ( );
		if ( !array_key_exists( $form_key, $countries ) ) {
			$def = self::getFormDefinition( $form_key );
			if ( !$def ) {
				$countries[$form_key] = 'INVALID';
			} else if ( array_key_exists( 'countries', $def ) ) {
				$countries[$form_key] = $def['countries'];
			} else {
				$countries[$form_key] = 'ALL';
			}
		}

		if ( DataValidator::value_appears_in( $country_iso, $countries[$form_key] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks to see if the ffname supplied is a valid form for the rest of the supplied params.
	 * @param string $ffname The form name to check.
	 * @param string $country Optional country code filter
	 * @param string $currency Optional currency code filter
	 * @param string $payment_method Optional payment method filter
	 * @param string $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param boolean $recurring Whether or not we should return recurring forms. Default = false.
	 * @param string $gateway Optional gateway to force.
	 * @return bool True if the supplied form matches the requirements, otherwise false
	 */
	static function isValidForm( $ffname, $country = null, $currency = null, $payment_method = null, $payment_submethod = null, $recurring = false, $gateway = null ) {
		$forms = self::getAllValidForms( $country, $currency, $payment_method, $payment_submethod, $recurring, $gateway );
		if ( is_array( $forms ) && array_key_exists( $ffname, $forms ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Return an array of all the currently enabled gateways. 
	 *
	 * @return array of gateway identifiers.
	 */
	static function getAllEnabledGateways(){
		global $wgDonationInterfaceGatewayAdapters;

		$enabledGateways = array();
		foreach ( $wgDonationInterfaceGatewayAdapters as $gatewayClass ) {
			if ( $gatewayClass::getGlobal( 'Enabled' ) ) {
				$enabledGateways[] = $gatewayClass::getIdentifier();
			}
		}
		return $enabledGateways;
	}

	/**
	 * In the event that we have more than one valid form, we need to figure out
	 * which one we ought to be using.
	 * In the absense of any data regarding form preferences, we should pick one
	 * that appears to be localized for whatever we requested.
	 * If we still have more than one, take the one with the most payment
	 * submethods.
	 * If we *still* have more than one, just... take the top or something.
	 * @param array $valid_forms All the forms that are valid for the parameters
	 * we've used.
	 * @param $currency
	 * @param $country
	 * @return mixed
	 */
	static function pickOneForm( $valid_forms, $currency, $country ){
		if ( count( $valid_forms ) === 1 ){
			reset( $valid_forms );
			return key ( $valid_forms );
		}
		
		//general idea: If one form has constraints for the following ordered
		//keys, and some forms do not have that constraint, prefer the one with 
		//the explicit constraints. 
		//But, it naturally got more complicated when I started considering the
		//ivnerse. 
		$keys = array(
			'currencies' => $currency,
			'countries' => $country,
		);
		foreach ( $keys as $key => $look ) { 
		//got to loop on keys first, as valid_forms loop will hopefully shrink as we're going.
			$failforms = array();
			foreach ( $valid_forms as $form_name => $meta ){
				if ( ( !is_null($look) && !array_key_exists( $key, $meta ) )
					|| is_null($look) && array_key_exists( $key, $meta ) ){
					$failforms[] = $form_name;
				}
			}
			if ( !empty( $failforms ) && count( $failforms ) != count( $valid_forms ) ) {
				//Kill everybody who didn't have it, because somebody totally did.
				foreach ( $failforms as $failform ){
					unset( $valid_forms[$failform] );
				}
			}
			if ( count( $valid_forms ) === 1 ){
				reset( $valid_forms );
				return key ( $valid_forms );
			}
		}
		
		//now, go for the one with the most explicitly defined payment submethods. 
		$submethod_counter = array();
		foreach ( $valid_forms as $form_name => $meta ){
			$submethod_counter[$form_name] = 0;
			foreach ( $meta['payment_methods'] as $method ){
				$submethod_counter[$form_name] += count( $method );
			}			
		}
		arsort( $submethod_counter, SORT_NUMERIC );
		$max = 0;
		foreach ( $submethod_counter as $form_name => $count ){
			if ( $count > $max ){ 
				$max = $count; //after the arsort, this will happen the first time and that's it.
			}
			if ( $count < $max ){
				unset( $valid_forms[$form_name] );
			}
		}
		
		if ( count( $valid_forms ) === 1 ){
			reset( $valid_forms );
			return key ( $valid_forms );
		}
		
		//Hell: we're still here. Throw a freaking dart
		$total_weight = 0;
		foreach ( array_keys( $valid_forms ) as $form_name ) {
			if ( !array_key_exists( 'selection_weight', $valid_forms[$form_name] ) ) {
				$valid_forms[$form_name]['selection_weight'] = 100;
			}
			$form_weight = $valid_forms[$form_name]['selection_weight'];
			if ( $form_weight === 0 ) {
				unset( $valid_forms[$form_name] );
				continue;
			}
			$total_weight += $form_weight;
		}
		$count = 0;
		$randN = rand( 1, $total_weight );
		foreach ( $valid_forms as $form_name => $meta ) {
			$count += $meta['selection_weight'];
			if ( $randN <= $count ) {
				return $form_name;
			}
		}
		return null;
	}

	/**
	 * Get the best defined error form for all your error form needs!
	 * ...based on gateway, method, and optional submethod.
	 * @global array $wgDonationInterfaceAllowedHtmlForms Contains all whitelisted forms and meta data
	 * @param string $gateway The gateway used for the payment that failed
	 * @param string $payment_method The code for the payment method that failed
	 * @param string $payment_submethod Code for the payment submethod that failed
	 * @throws RuntimeException
	 */
	static function getBestErrorForm( $gateway, $payment_method, $payment_submethod = null ) {
		global $wgDonationInterfaceAllowedHtmlForms;
		$error_forms = array ( );
		foreach ( $wgDonationInterfaceAllowedHtmlForms as $ffname => $data ) {
			if ( array_key_exists( 'special_type', $data ) && $data['special_type'] === 'error' ) {
				$is_match = true;
				# XXX what is this magick?  Do something less evil.
				$group = 2; //default group
				//check to make sure it fits our needs.
				if ( is_array( $data['gateway'] ) ) {
					if ( !in_array( $gateway, $data['gateway'] ) ) {
						$is_match = false;
					}
				} else { //not an array
					if ( $data['gateway'] !== $gateway ) {
						$is_match = false;
					}
				}

				if ( $is_match ) {
					//if no payment methods specified in the error form, we don't have to throw it away...
					if ( array_key_exists( 'payment_methods', $data ) ) {
						if ( !array_key_exists( $payment_method, $data['payment_methods'] ) ) {
							//key exists, but we're not in there.
							$is_match = false;
						} else {
							$group = 1; //payment method specificity
							if ( !is_null( $payment_submethod ) && !in_array( $payment_submethod, $data['payment_methods'] ) && !in_array( 'ALL', $data['payment_methods'] ) ) {
								$is_match = false;
							} else {
								$group = 0; //payment submethod specificity
							}
						}
					}
				}

				if ( $is_match ) {
					$error_forms[$group][$ffname] = $data;
				}
			}
		}

		if ( !sizeof( $error_forms ) ) {
			// TODO: Throw an RuntimeException, once we've updated payments mw-core.
			throw new MWException( __FUNCTION__ . "No error form found for gateway '$gateway', method '$payment_method', submethod '$payment_submethod'" );
		}

		//sort the error_forms by $group; get the most specific form defined
		ksort( $error_forms );

		//Currently, $error_forms[$group][$ffname] = $data,
		//with the most specific error forms in the top $group.
		//So, get rid of all but the top group and collapse.
		$error_forms = reset( $error_forms ); //top group
		//now, $error_forms[$ffname] = $data. So, return the top key.
		reset( $error_forms ); //top form from that group (there must be at least one for the key to exist)
		return key( $error_forms );
	}

}
