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
		$gateway = $this->getRequest()->getVal( 'gateway', null );
		
		//This is clearly going to go away before we deploy this bizniss. 
		$testNewGetAll = $this->getRequest()->getVal( 'testGetAll', false );
		if ( $testNewGetAll ){
			$forms = self::getAllValidForms( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, $gateway );
			echo "<pre>" . print_r( $forms, true ) . "</pre>";
			$form = self::pickOneForm( $forms, $currency, $country );
			echo "<pre>I choose you, " . print_r( $form, true) . "!</pre>";
			echo "<pre>Trying: " . ucfirst($forms[$form]['gateway']) . "Gateway</pre>";
			die();
		}

		$forms = self::getAllValidForms( $country, $currency, $paymentMethod, $paymentSubMethod, $recurring, $gateway );
		$form = self::pickOneForm( $forms, $currency, $country );

		// And... construct the URL
		$params = array(
			'form_name' => "RapidHtml",
			'appeal' => "JimmyQuote",
			'ffname' => $form,
			'recurring' => $recurring,
		);

		if( DataValidator::value_appears_in( 'redirect', $forms[$form] ) ){
			$params['redirect'] = '1';
		}

		// Pass any other params that are set. We do not skip ffname or form_name because
		// we wish to retain the query string override.
		$excludeKeys = array( 'paymentmethod', 'submethod', 'title', 'recurring' );
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			// Skip the required variables
			if ( !in_array( $key, $excludeKeys ) ) {
				$params[$key] = $value;
			}
		}

		// set the default redirect
		$redirectURL = $this->getTitleFor( ucfirst($forms[$form]['gateway']) . "Gateway" )->getLocalUrl( $params );

		// This is an error condition, so we return something reasonable
		// TODO: Figure out something better to do
//		$redirectURL = "https://wikimediafoundation.org/wiki/Ways_to_Give";

		// Perform the redirection
		$this->getOutput()->redirect( $redirectURL );
	}
	
	/**
	 * Gets all the valid forms that match the provided paramters. 
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
		global $wgDonationInterfaceAllowedHtmlForms, $wgDonationInterfaceClassMap;
		$forms = $wgDonationInterfaceAllowedHtmlForms;
		
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

			/** @var GatewayAdapter $adapterName */
			$adapterName = $wgDonationInterfaceClassMap[$meta['gateway']];

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

			// Filter on currency; and if it's too generic we add what the adapter thinks it can support
			if ( $meta['currencies'] === 'ALL' ) {
				$meta['currencies'] = array( '+' => $adapterName::getCurrencies() );
			} elseif( array_key_exists( '-', $meta['currencies'] ) && !array_key_exists( '+', $meta['currencies'] ) ) {
				$meta['currencies']['+'] = $adapterName::getCurrencies();
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
			
			//filter on recurring
			if ( DataValidator::value_appears_in( 'recurring', $meta ) !== (bool)$recurring ) {
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
		
		//Hell: we're still here. Throw a freaking dart. 
		reset( $valid_forms );
		return key ( $valid_forms );
	}
}
