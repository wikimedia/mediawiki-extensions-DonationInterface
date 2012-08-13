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

	function __construct() {
		parent::__construct( 'GatewayFormChooser' );
	}

	function execute( $par ) {

		// Set the country parameter
		$country = $this->getRequest()->getVal( 'country', null );
		$currency = $this->getRequest()->getVal( 'currency', null );
		$paymentMethod = $this->getRequest()->getVal( 'paymentmethod', null );
		$paymentSubMethod = $this->getRequest()->getVal( 'submethod', null );
		$recurring = $this->getRequest()->getVal( 'recurring', false );
		
		//This is clearly going to go away before we deploy this bizniss. 
		$testNewGetAll = $this->getRequest()->getVal( 'testGetAll', false );
		if ( $testNewGetAll ){
			$forms = self::getAllValidForms( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring );
			echo "<pre>" . print_r( $forms, true ) . "</pre>";
			$form = self::pickOneForm( $forms, $currency, $country );
			echo "<pre>I choose you, " . print_r( $form, true) . "!</pre>";
			die();
		}

		$formSpec = $this->obtainFormSpecification( $country, $currency, $paymentMethod,
			$paymentSubMethod );
		
		// Now do something with the specification
		$params = array();

		if ($formSpec) {
			// TODO: Make this a little more intelligent than just choosing the first one
			switch( $formSpec['gateways'][0] ){
				case 'globalcollect' :
					$gateway = "GlobalCollectGateway";
					break;

				default :
					$gateway = "GlobalCollectGateway"; // everything defaults here
					break;
			}

			// TODO: Also more intelligent here
			$formname = $formSpec['forms'][0];

			// And... construct the URL
			$params = array(
				'form_name' => "RapidHtml",
				'appeal' => "JimmyQuote",
				'ffname' => $formname
			);

			// Pass any other params that are set. We do not skip ffname or form_name because
			// we wish to retain the query string override.
			$excludeKeys = array( 'paymentmethod', 'submethod', 'title' );
			foreach ( $this->getRequest()->getValues() as $key => $value ) {
				// Skip the required variables
				if ( !in_array( $key, $excludeKeys ) ) {
					$params[$key] = $value;
				}
			}

			// set the default redirect
			$redirectURL = $this->getTitleFor( $gateway )->getLocalUrl( $params );

		} else {
			// This is an error condition, so we return something reasonable
			// TODO: Figure out something better to do

			$redirectURL = "https://wikimediafoundation.org/wiki/Ways_to_Give";
		}

		// Perform the redirection
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Filters through all possible forms and returns the set that matches the arguments.
	 *
	 * @param      $country
	 * @param      $currency
	 * @param      $paymentMethod
	 * @param null $paymentSubMethod
	 *
	 * @return bool or array Will be FALSE if nothing matched the filter. Otherwise array with
	 *  keys 'gateways' and 'forms'
	 */
	function obtainFormSpecification( $country, $currency, $paymentMethod,
		$paymentSubMethod = null ) {

		// This map should be $[country][currency][method][] with two base keys of gateway
		// and form. These keys may be arrays themselves.
		global $wgDonationInterfaceFormMap;

		// Make sure all the required keys exist
		$country = $this->getKey( $country, 'default', $wgDonationInterfaceFormMap );

		if ($country) {
			$currency = $this->getKey( $currency, 'default', $wgDonationInterfaceFormMap[$country] );

			if ($currency) {
				$method = $this->getKey( $paymentMethod . "_" . $paymentSubMethod,
					$paymentMethod, $wgDonationInterfaceFormMap[$country][$currency] );
			}
		}

		// Determine how far we got through the filter
		if ( $country === null || $currency === null || $method === null ) {
			return false;
		}

		// Get the final structure
		$formSpec = $wgDonationInterfaceFormMap[$country][$currency][$method];

		// Final check to make sure we have something to return
		if( ( count( $formSpec['gateways'] ) > 0 ) &&
			( count( $formSpec['forms'] ) > 0 )
		) {
			return $formSpec;
		} else {
			return false;
		}
	}

	/**
	 * Return one of $key, $default, or null depending on what exists in the $array.
	 *
	 * @param $key      The first key to look for
	 * @param $default  The second key to look for
	 * @param $array    The haystack to search in
	 *
	 * @return null or string
	 */
	function getKey( $key, $default, $array ) {
		if ( array_key_exists ( $key, $array ) ) {
			return $key;
		} elseif ( array_key_exists ( $default, $array ) ) {
			return $default;
		} else {
			return null;
		}
	}
	
	
	/**
	 * Gets all the valid forms that match the provided paramters. 
	 * @global array $wgDonationInterfaceAllowedHtmlForms Contains all whitelisted forms and meta data
	 * @param string $country Optional country code filter
	 * @param string $currency Optional currency code filter
	 * @param string $payment_method Optional payment method filter
	 * @param string $payment_submethod Optional payment submethod filter. THIS WILL ONLY WORK IF YOU ALSO SEND THE PAYMENT METHOD.
	 * @param boolean $recurring Whether or not we should return recurring forms. Default = false.
	 * @return type 
	 */
	static function getAllValidForms( $country = null, $currency = null, $payment_method = null, $payment_submethod = null, $recurring = false ){
		global $wgDonationInterfaceAllowedHtmlForms;
		$forms = $wgDonationInterfaceAllowedHtmlForms;
		
		//then remove the ones that we don't want. 
		$valid_gateways = self::getAllEnabledGateways();
		foreach ( $forms as $name => $meta ){
			$unset = false;
			
			//filter on enabled gateways
			if ( !array_key_exists( 'gateway', $meta ) ){
				unset ( $forms[$name] );
				continue; 
			}
			//if it's an array, any one will do. 
			if ( is_array( $meta['gateway'] ) ){
				$found = false;
				foreach ( $meta['gateway'] as $index => $value ){
					if ( DataValidator::value_appears_in( $value, $valid_gateways ) ){
						$found = true;
						break;
					}
				}
				if ( !$found ){
					$unset = true;
				}
			} else {
				if ( !DataValidator::value_appears_in( $meta['gateway'], $valid_gateways ) ){
					$unset = true;
				}
			}
			
			if ( $unset ){
				unset( $forms[$name] );
				continue;
			}
			
			//filter on country
			if ( !is_null( $country ) ){
				if ( array_key_exists( 'countries', $meta ) ){ //totally okay if it doesn't.
					if ( array_key_exists( '+', $meta['countries'] ) ){
						if ( !DataValidator::value_appears_in( $country, $meta['countries']['+'] ) ){
							unset( $forms[$name] );
							continue;
						}
					}
					if ( array_key_exists( '-', $meta['countries'] ) ){
						if ( DataValidator::value_appears_in( $country, $meta['countries']['-'] ) ){
							unset( $forms[$name] );
							continue;
						}
					}
				}
			}

			//filter on currency
			if ( !is_null( $currency ) ){
				if ( array_key_exists( 'currencies', $meta ) ){ //totally okay if it doesn't.
					if ( array_key_exists( '+', $meta['currencies'] ) ){
						if ( !DataValidator::value_appears_in( $currency, $meta['currencies']['+'] ) ){
							unset( $forms[$name] );
							continue;
						}
					}
					if ( array_key_exists( '-', $meta['currencies'] ) ){
						if ( DataValidator::value_appears_in( $currency, $meta['currencies']['-'] ) ){
							unset( $forms[$name] );
							continue;
						}
					}
				}
			}
			
			//filter on payment method
			if ( !array_key_exists( 'payment_methods', $meta ) ){ 
				unset( $forms[$name] );
				continue;
			}
			if ( !is_null( $payment_method ) ){
				if ( !array_key_exists( $payment_method, $meta['payment_methods'] ) ){
					unset( $forms[$name] );
					continue;
				}
				
				//filter on payment submethod
				//CURSES! I didn't want this to be buried down in here, but I guess it's sort of reasonable. Ish.
				if ( !is_null( $payment_submethod ) ){
					if ( !DataValidator::value_appears_in( $payment_submethod, $meta['payment_methods'][$payment_method] )
						&& !DataValidator::value_appears_in( 'ALL', $meta['payment_methods'][$payment_method] ) ){
						unset( $forms[$name] );
						continue;
					}
				}
			}
			
			//filter on recurring
			if ( $recurring && !DataValidator::value_appears_in( 'recurring', $meta ) ){
				unset( $forms[$name] );
				continue;
			}
			if ( !$recurring && DataValidator::value_appears_in( 'recurring', $meta ) ){
				unset( $forms[$name] );
				continue;
			}
			
		}
		return $forms;
	}
	
	/**
	 * Return an array of all the currently enabled gateways. 
	 * I had hoped there would be more to this...
	 * @global type $wgDonationInterfaceEnabledGateways
	 * @return array
	 */
	static function getAllEnabledGateways(){
		global $wgDonationInterfaceEnabledGateways;
		return $wgDonationInterfaceEnabledGateways;
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
		
		//Hell: we're still here. Throw a freaking dart. 
		reset( $valid_forms );
		return key ( $valid_forms );
	}
}
