<?php

abstract class Gateway_Form {

	/**
	 * @var GatewayAdapter
	 */
	protected $gateway;

	/**
	 * @var GatewayPage
	 */
	protected $gatewayPage;
	// FIXME: decouple form rendering from both of the above.
	// Instead, make a FormParameters class with errors, donor info, settings, etc.

	/**
	 * @var string
	 */
	protected $scriptPath;

	/**
	 * Required method for returning the full HTML for a form.
	 *
	 * Code invoking forms will expect this method to be set.  Requiring only
	 * this method allows for flexible form generation inside of child classes
	 * while also providing a unified method for returning the full HTML for
	 * a form.
	 * @return string The entire form HTML
	 */
	abstract function getForm();

	/**
	 * Get these objects using "new" with no parameters.
	 */
	public function __construct() {}

	public function setGateway( GatewayType $gateway ) {
		$this->gateway = $gateway;
	}

	public function setGatewayPage( GatewayPage $gatewayPage ) {
		$this->gatewayPage = $gatewayPage;
		$this->scriptPath = $gatewayPage->getContext()->getConfig()->get( 'ScriptPath' );
	}

	/**
	 * Determine the 'no cache' form action
	 *
	 * This mostly exists to ensure that the form does not try to use AJAX to
	 * overwrite certain hidden form params that are normally overwitten for
	 * cached versions of the form.
	 * @return string $url The full URL for the form to post to
	 */
	protected function getNoCacheAction() {

		$url = $this->gatewayPage->getRequest()->getFullRequestURL();
		$url_parts = wfParseUrl( $url );
		if ( isset( $url_parts['query'] ) ) {
			$query_array = wfCgiToArray( $url_parts['query'] );
		} else {
			$query_array = array( );
		}

		// ensure that _cache_ does not get set in the URL
		unset( $query_array['_cache_'] );

		// make sure no other data that might overwrite posted data makes it into the URL

		$all_form_data = $this->gateway->getData_Unstaged_Escaped();
		$keys_we_need_for_form_loading = array(
			'form_name',
			'ffname',
			'country',
			'currency',
			'language'
		);
		$form_data_keys = array_keys( $all_form_data );

		foreach ( $query_array as $key => $value ){
			if ( in_array( $key, $form_data_keys ) ){
				if ( !in_array( $key, $keys_we_need_for_form_loading ) ){
					unset( $query_array[$key] );
				} else {
					$query_array[$key] = $all_form_data[$key];
				}
			}
		}

		// construct the submission url
		$title = $this->gatewayPage->getPageTitle();
		return wfAppendQuery( $title->getLocalURL(), $query_array );
	}

	public function getResources() {
		return array();
	}

	/**
	 * All the things that need to be loaded in the head with link tags for
	 * styling server-rendered html
	 *
	 * @return array
	 */
	public function getStyleModules() {
		return array();
	}

	/**
	 * Given an absolute file path, returns path relative to extension base dir.
	 * @param string $absolutePath
	 * @return string path relative to DonationInterface/
	 */
	protected function sanitizePath( $absolutePath ) {
		$base_pos = strpos( $absolutePath, 'DonationInterface' );
		if ( $base_pos !== false ) {
			return substr( $absolutePath, $base_pos );
		}
		return $absolutePath;
	}
}
