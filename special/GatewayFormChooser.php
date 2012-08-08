<?php
/*
 * GatewayFormChooser acts as a gateway-agnostic landing page for second-step forms.
 * When passed a country, currency, and payment method combination, it determines the
 * appropriate form based on the forms defined for that combination taking into account
 * the currently available payment processors.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 * @author Matt Walker <mwalker@wikimedia.org>
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
}
