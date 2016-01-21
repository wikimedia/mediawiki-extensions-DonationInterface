<?php

abstract class Gateway_Form {
	/**
	 * An array of form errors, passed from the gateway form object
	 * @var array
	 */
	public $form_errors;

	/**
	 * Gateway-specific logger
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @var GatewayAdapter
	 */
	protected $gateway;

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

	public function __construct( $gateway ) {

		$this->gateway = $gateway;
		$this->scriptPath = $this->gateway->getContext()->getConfig()->get( 'ScriptPath' );
		$this->logger = DonationLoggerFactory::getLogger( $gateway );
		$gateway_errors = $this->gateway->getAllErrors();

		// @codeCoverageIgnoreStart
		if ( !is_array( $gateway_errors ) ){
			$gateway_errors = array();
		}
		// @codeCoverageIgnoreEnd

		$this->form_errors = array_merge( DataValidator::getEmptyErrorArray(), $gateway_errors );
	}

	/**
	 * Uses resource loader to load the form validation javascript.
	 */
	protected function loadValidateJs() {
		$this->gateway->getContext()->getOutput()->addModules( 'di.form.core.validate' );
	}

	/**
	 * Generate HTML for <noscript> tags
	 * For displaying when a user does not have Javascript enabled in their browser.
	 */
	protected function getNoScript() {
		$noScriptRedirect = $this->gateway->getGlobal( 'NoScriptRedirect' );

		$form = '<noscript>';
		$form .= '<div id="noscript">';
		$form .= '<p id="noscript-msg">' . wfMessage( 'donate_interface-noscript-msg' )->escaped() . '</p>';
		if ( $noScriptRedirect ) {
			$form .= '<p id="noscript-redirect-msg">' . wfMessage( 'donate_interface-noscript-redirect-msg' )->escaped() . '</p>';
			$form .= '<p id="noscript-redirect-link"><a href="' . htmlspecialchars( $noScriptRedirect ) . '">' . htmlspecialchars( $noScriptRedirect ) . '</a></p>';
		}
		$form .= '</div>';
		$form .= '</noscript>';
		return $form;
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

		$url = $this->gateway->getRequest()->getFullRequestURL();
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
		$title = $this->gateway->getContext()->getTitle();
		return wfAppendQuery( $title->getLocalURL(), $query_array );
	}

	/**
	 * Create and return the Verisign logo (small size) form element.
	 */
	protected function getSmallSecureLogo() {
		$form = '<table id="secureLogo" width="130" border="0" cellpadding="2" cellspacing="0" title="' . wfMessage( 'donate_interface-securelogo-title' )->text() . '">';
		$form .= '<tr>';
		$form .= '<td width="130" align="center" valign="top"><script type="text/javascript" src="' . htmlentities( 'https://seal.verisign.com/getseal?host_name=payments.wikimedia.org&size=S&use_flash=NO&use_transparent=NO&lang=en' ) . '"></script><br /><a href="http://www.verisign.com/ssl-certificate/" target="_blank"  style="color:#000000; text-decoration:none; font:bold 7px verdana,sans-serif; letter-spacing:.5px; text-align:center; margin:0px; padding:0px;">' . wfMessage( 'donate_interface-secureLogo-text' )->text() . '</a></td>';
		$form .= '</tr>';
		$form .= '</table>';
		return $form;
	}

	/**
	 * Pulls normalized and escaped data from the $gateway object.
	 * For more information, see GatewayAdapter::getData_Unstaged_Escaped in
	 * $IP/extensions/DonationData/gateway_common/gateway.adapter.php
	 * @param string $key The value to fetch from the adapter.
	 * @return mixed The escaped value in the adapter, or null if none exists.
	 * Note: The value could still be a blank string in some cases.
	 */
	protected function getEscapedValue( $key ) {
		return $this->gateway->getData_Unstaged_Escaped( $key );
	}

	public function getResources() {
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
