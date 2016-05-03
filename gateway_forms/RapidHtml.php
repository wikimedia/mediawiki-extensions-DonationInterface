<?php

class Gateway_Form_RapidHtml extends Gateway_Form {

	/**
	 * Full path of HTML form to load
	 * @var string
	 */
	protected $html_file_path = '';
	
	/**
	 * Whitelisted base directory from which the HTML form is loading.
	 * This may not necessarily be just the path without the filename: It's 
	 * probably back farther than that. 
	 * @var string
	 */
	protected $html_base_dir = '';

	/**
	 * The directory that is the default/universally shared base dir.
	 * So, $wgDonationInterfaceHtmlFormDir
	 * @var string
	 */
	protected $html_default_base_dir = '';

	/**
	 * Tokens used in HTML form for data replacement
	 * 
	 * Note that these NEED to be in the same order as the variables in $data in 
	 * order for str_replace to work as expected
	 * @var array
	 */
	protected $data_tokens = array(
		'@amount', // => $amount,
		'@amountOther', // => WebRequest->getText( 'amountOther' ),
		'@appeal',
		'@email', // => WebRequest->getText( 'email' ),
		'@fname', // => WebRequest->getText( 'fname' ),
		'@lname', // => WebRequest->getText( 'lname' ),
		'@street_supplemental', // => WebRequest->getText( 'street_supplemental' ), MUST BE BEFORE @street
		'@street', // => WebRequest->getText( 'street' ),
		'@city', // => WebRequest->getText( 'city' ),
		'@state', // => WebRequest->getText( 'state' ),
		'@zip', // => WebRequest->getText( 'zip' ),
		'@country', // => WebRequest->getText( 'country' ),
		'@card_num', // => str_replace( ' ', '', WebRequest->getText( 'card_num' ) ),
		'@card_type', // => WebRequest->getText( 'card_type' ),
		'@expiration', // => WebRequest->getText( 'mos' ) . substr( WebRequest->getText( 'year' ), 2, 2 ),
		'@cvv', // => WebRequest->getText( 'cvv' ),
		'@currency_code', //'currency_code' => WebRequest->getText( 'currency_code' ),
		'@payment_method', // => WebRequest->getText( 'payment_method' ),
		'@order_id', // => $order_id,
		'@referrer', // => ( WebRequest->getVal( 'referrer' ) ) ? WebRequest->getVal( 'referrer' ) : WebRequest->getHeader( 'referer' ),
		'@utm_source', // => self::getUtmSource(),
		'@utm_medium', // => WebRequest->getText( 'utm_medium' ),
		'@utm_campaign', // => WebRequest->getText( 'utm_campaign' ),
		// try to honor the user-set language (uselang), otherwise the language set in the URL (language)
		'@language', // => WebRequest->getText( 'uselang', WebRequest->getText( 'language' ) ),
		'@email-opt', // => WebRequest->getText( 'email-opt' ),
		'@test_string', // => WebRequest->getText( 'process' ), // for showing payflow string during testing
		'@wmf_token', // => $wmf_token,
		'@contribution_tracking_id', // => WebRequest->getText( 'contribution_tracking_id' ),
		'@data_hash', // => WebRequest->getText( 'data_hash' ),
		'@action', // => WebRequest->getText( 'action' ),
		'@gateway', // => 'globalcollect', // this may need to become dynamic in the future
		'@owa_session', // => WebRequest->getText( 'owa_session', null ),
		'@owa_ref', // => $owa_ref,
        // Direct Debit Fields
		'@account_number',
		'@authorization_id',
		'@account_name',
		'@bank_code',
		'@bank_name',
		'@bank_check_digit',
		'@branch_code',
		// Boletos
		'@fiscal_number',
		// Not actually data tokens, but available to you in html form:
		// @script_path -> maps to $wgScriptPath 
		// @action -> generate correct form action for this form
		// @appeal -> name of the appeal text to load
		// @appeal_title -> name of the appeal title to load
		// @verisign_logo -> placeholder to load the secure verisign logo
		// @select_country -> generates a select containing all country names
		// @noscript -> Some stuff in a noscript node
		'@ffname_retry', //form name for retries (used by error pages)

		// Worldpay Variables
		'@wp_one_time_token',
		'@wp_process_url',
	);

	/**
	 * Error field names used as tokens
	 * @var array
	 */
	protected $error_tokens = array(
		'#general',
		'#retryMsg',
		'#amount',
		'#card_num',
		'#card_type',
		'#cvv',
		'#fname',
		'#lname',
		'#city',
		'#country',
		'#street',
		'#street_supplemental',
		'#state',
		'#zip',
		'#email',
		'#fiscal_number',
	);

	public function __construct( &$gateway ) {
		global $wgDonationInterfaceHtmlFormDir;
		parent::__construct( $gateway );
		$form_errors = $this->form_errors;

		$this->loadValidateJs();

		$ffname = $this->gateway->getData_Unstaged_Escaped( 'ffname' );
		// Get error passed via query string
		$error = $this->gateway->getRequest()->getText( 'error' );
		if ( $error ) {
			// We escape HTML here since only quotes are escaped later
			$form_errors['general'][] = htmlspecialchars( $error );
		}

		// only keep looking if we still haven't found a form that works
		if ( empty( $this->html_file_path ) ){
			try {
				$this->set_html_file_path( $ffname );
			} catch ( Exception $mwe ) {
				$message = "Could not load form '$ffname'";
				$this->logger->error( $message );
				$this->set_html_file_path( 'error-noform' );
			}
		}

		// fix general form error messages so it's not an array of msgs
		if ( is_array( $form_errors['general'] ) && count( $form_errors['general'] ) ) {
			$general_errors = "";
			foreach ( $form_errors['general'] as $general_error ) {
				$general_errors .= "$general_error<br />";
			}

			$form_errors['general'] = $general_errors;
		}

		$this->html_default_base_dir = $wgDonationInterfaceHtmlFormDir;
	}

	/**
	 * Return the HTML form with data added
	 */
	public function getForm() {
		$html = $this->load_html();
		$html = $this->replace_blocks( $html );
		return $this->add_data( $html );
	}

	/**
	 * Load the HTML form from a file into a string
	 * @return string
	 */
	public function load_html() {
		return file_get_contents( $this->html_file_path );
	}

	/**
	 * Add data into the HTML form
	 * 
	 * @param string $html Form with tokens as placehodlers for data
	 * @return string The HTML form with real data in it
	 */
	public function add_data( $html ) {

		/**
		 * This is a hack and should be replaced with something more performant.
		 */
		$form = $html;

		// handle form action
		$form = str_replace( "@action", $this->getNoCacheAction(), $form );

		// replace data
		foreach ( $this->data_tokens as $token ) {
			$key = substr( $token, 1, strlen( $token )); //get the token string w/o the '@'

			if ( $this->getEscapedValue( $key ) ) {
				$replace = $this->getEscapedValue( $key );
			} else {
				$replace = '';
			}
			$form = str_replace( $token, $replace, $form );
		}

		// replace errors|escape with escaped versions
		$escape_error_tokens = array();
		foreach ( $this->error_tokens as $token ) {
			$escape_error_tokens[] = "$token|escape";
		}
		$escape_errors = array();
		
		//TODO: $raw_errors might not be used anywhere. This is a band-aid to
		//fix the thing throwing notices everywhere. We should determine if this
		//is even a thing anymore, and nuke appropriately. 
		$raw_errors = array();
		foreach ( $this->form_errors as $error ) {
			$error_c = str_replace( array("\r\n", "\n", "\r"), " ", $error );
			if( is_array( $error ) ){
				$error_c = implode( " ", $error_c );
				$error = implode( " ", $error );
			}
			$escape_errors[] = addslashes($error_c);
			$raw_errors[] = $error;
		}
		$form = str_replace($escape_error_tokens, $escape_errors, $form);

		// replace standard errors
		$form = str_replace($this->error_tokens, $raw_errors, $form);
		
		// handle script path
		$form = str_replace( "@script_path", $this->scriptPath, $form );

		// handle verisign logo
		$form = str_replace( "@verisign_logo", $this->getSmallSecureLogo(), $form );

		// handle country drop-down
		$form = str_replace( "@select_country", $this->getCountryDropdown(), $form );

		// handle noscript
		$form = str_replace( "@noscript", $this->getNoScript(), $form );

		$form = $this->fix_dropdowns( $form );

		return $this->add_messages( $form );
	}

	/**
	 * Add messages into the HTML form
	 *
	 * @param string $html Form with tokens as placeholders for messages
	 * @return string The HTML form containing translated messages
	 */
	public function add_messages( $html ) {
		global $wgDonationInterfaceMessageLinks;
		if( $this->gateway->getRequest()->getText( 'rapidhtml_debug', 'false' ) == 'true' ){
			# do not replace tokens
			return $html;
		}

		# replace interface messages
		# doing this before transclusion so that tokens can be passed as params (e.g. @language)
		$matches = array();
		preg_match_all( "/%([a-zA-Z0-9_-]+)(|(?:(?!%).)*)%/", $html, $matches );

		foreach( $matches[ 1 ] as $i => $msg_key ){
			if(isset($matches[ 2 ][ $i ]) && $matches[ 2 ][ $i ] != ''){
				$params = explode( '|', trim( $matches[ 2 ][ $i ], '|' ) );

				// replace link URLs with the global variable setting and pass language and country
				foreach( $params as $k => $p ){
					if( $p == "url" && isset( $wgDonationInterfaceMessageLinks[ $msg_key ] ) ){
						$params[ $k ] =  $wgDonationInterfaceMessageLinks[ $msg_key ];
						if( strpos( $params[ $k ], '?' ) >= 0 ){
							$params[ $k ] .= '&';
						} else {
							$params[ $k ] .= '?';
						}
						$params[ $k ] .= "language=" . $this->getEscapedValue( 'language' ) . "&country=" . $this->getEscapedValue( 'country' );
					}
				}
				// TODO: add support for message variations here as well
				$html = str_replace( $matches[ 0 ][ $i ], wfMessage( $msg_key, $params )->text(), $html );
			} else {
				// look for a country variant of the message and use that if found
				$msg_text = MessageUtils::getCountrySpecificMessage(
					$msg_key,
					$this->getEscapedValue( 'country' ),
					$this->getEscapedValue( 'language' )
				);
				$html = str_replace( '%' . $msg_key . '%', $msg_text, $html );
			}
		}

		# do any requested tranclusion of templates
		$matches = array();
		preg_match_all( "/{{((?:(?!}).)+)}}/", $html, $matches );
		
		foreach( $matches[ 0 ] as $template ){
			# parse the template and replace in the html
			$output = $this->gateway->getContext()->getOutput();
			$html = str_replace( $template, $output->parse( $template ), $html );
		}
		return $html;
	}

	/**
	 * Replaces basic template blocks in forms with the template elements
	 *
	 * @param string $html Form with tokens as placeholders for messages
	 * @return string The HTML form containing translated messages
	 */
	public function replace_blocks( $html ){
		if( $this->gateway->getRequest()->getText( 'rapidhtml_debug', 'false' ) == 'true' ){
			# do not replace tokens
			return $html;
		}

		# replace template blocks
		# doing this before transclusion so that tokens can be used in the templates
		$matches = array();
		# look for the start block and switching variable
		preg_match_all( "/{%\s*block ([a-zA-Z0-9_-]+)\s*([a-zA-Z0-9_-]*)\s*%}/i", $html, $matches );
		
		foreach ( $matches[1] as $i => $key ) {
			# $matches[ 1 ] is specified in the code, not user input
			$filepath[0] = $this->html_base_dir . '/_' . $matches[1][$i] . '/';
			if ($this->html_default_base_dir != $this->html_base_dir){
				$filepath[1] = $this->html_default_base_dir . '/_' . $matches[1][$i] . '/';
			}

			$var = 'default';

			# check to see if the parameter is, in fact, an element in DonationData
			$param = $this->getEscapedValue( $matches[2][$i] );
			if ( $param && !is_array( $param ) ) {
				# get the value of the element and super-escape
				$var = MessageUtils::makeSafe( $param, 'default' );
			}

			# oh, and we only allow with the extension .html
			# take that h@k3rs
			$found_file = false;
			foreach ( $filepath as $try_this_path ) {
				if (!$found_file){
					if ( file_exists( $try_this_path . $var . '.html' ) ) {
						# replace the template block with the specific template
						$found_file = $try_this_path . $var . '.html';
					} elseif ( file_exists( $try_this_path . 'default.html' ) ) {
						# replace the template block with the default template
						$found_file = $try_this_path . 'default.html';
					}
				}
			}
			if ( $found_file ){
				$template = $this->replace_blocks( file_get_contents( $found_file ) );

				$relative_path = $this->sanitizePath( $found_file );

				$template = "<!-- Generated from: {$relative_path} -->{$template}<!-- end {$relative_path} -->";
				$html = str_replace( $matches[0][$i], $template, $html );
			} else {
				# replace the template call with nothing at all
				$html = str_replace( $matches[0][$i], '', $html );
			}
		}
		return $html;
	}

	/**
	 * Set dropdowns to "selected' where appropriate
	 *
	 * @TODO: We shouldn't be adding a selected attribute to the <select>
	 * 
	 * @param $html
	 * @return string
	 */
	public function fix_dropdowns( $html ) {
		$matches = null;
		preg_match_all( '|<select.*>.*?</select>|is', $html, $matches );

		$numMatches = count( $matches[0] );

		//@TODO: When we have more time, deal with matches that are
		//commented out in very specific ways in the source template.
		//Can cause explode.

		for ( $i = 0; $i < $numMatches; $i++ ) {

			$domthingy = new DOMDocument();
			$domthingy->loadHTML( $matches[0][$i] );

			$select_element = $domthingy->getElementsByTagName( 'select' )->item( 0 );
			$select_element->removeAttribute( 'value' );
			$id = $select_element->getAttribute( 'id' );
			$value = false;
			if ( $id ) {
				$value = $this->getEscapedValue( $id );
			}

			if ( $value ) {
				$optionlist = $domthingy->getElementsByTagName( 'option' );
				$option_count = $optionlist->length;

				for ( $j = 0; $j < $option_count; $j++ ) {
					if ( $optionlist->item( $j )->getAttribute( 'value' ) === $value ) {
						$optionlist->item( $j )->setAttribute( 'selected', true );
					} else {
						$optionlist->item( $j )->removeAttribute( 'selected' );
					}
				}

				// Replace the whole block
				$html = str_replace( $matches[0][$i], $domthingy->saveHTML( $select_element ), $html );
			}
		}

		return $html;
	}

	/**
	 * Set the path to the HTML file for a requested rapid html form.
	 *
	 * @param string $form_key The array key defining the whitelisted form path to fetch from $wgDonationInterfaceAllowedHtmlForms
	 * @throws RuntimeException
	 */
	public function set_html_file_path( $form_key, $fatal = true ) {
		$allowedForms = $this->gateway->getGlobal( 'AllowedHtmlForms' );

		$problems = false;
		$debug_message = '';
		//make sure the requested form exists.
		if ( !array_key_exists( $form_key, $allowedForms ) ) {
			$debug_message = "Could not find form '$form_key'";
			$problems = true;
		} elseif ( empty( $allowedForms[$form_key] ) ) {
			$debug_message = "Form '$form_key' is disabled by configuration.";
			$problems = true;
		} elseif ( !array_key_exists( 'file', $allowedForms[$form_key] ) ) {
			$debug_message = "Form config for '$form_key' is missing 'file' value";
			$problems = true;
		} elseif ( !file_exists( $allowedForms[$form_key]['file'] ) ) {
			$debug_message = "Form template is missing for '$form_key', looking for file: {$allowedForms[$form_key]['file']}";
			$problems = true;
		}
		
		if ( !$problems ){
			//make sure the requested form is cleared for this gateway
			if ( !array_key_exists( 'gateway', $allowedForms[$form_key] ) ){
				$debug_message = "No defined gateways for '$form_key'";
				$problems = true;
			} else {
				$ident = $this->gateway->getIdentifier();
				if ( is_array( $allowedForms[$form_key]['gateway'] ) ){
					if ( !in_array( $ident, $allowedForms[$form_key]['gateway'] ) ){
						$debug_message = "$ident is not defined as an allowable gateway for '$form_key'";
						$problems = true;
					}
				} else {
					if ( $allowedForms[$form_key]['gateway'] != $ident ){
						$debug_message = "$ident is not defined as the allowable gateway for '$form_key'";
						$problems = true;
					}
				}
			}
		}
		
		if ( !$problems ){
			//now, figure out what whitelisted form directory this is a part of. 
			$allowedDirs = $this->gateway->getGlobal( 'FormDirs' );
			$dirparts = explode( '/', $allowedForms[$form_key]['file'] );
			$build = '';
			for( $i=0; $i<count( $dirparts ); ++$i ){
				if ( trim( $dirparts[$i] != '' ) ){
					$build .= '/' . $dirparts[$i];
				}
				if ( in_array( $build, $allowedDirs ) ){
					$this->html_base_dir = $build;
				}
			}

			if ( empty( $this->html_base_dir ) ){
				$debug_message = "No valid html_base_dir for '$form_key' - '$build' was not whitelisted.";
				$problems = true;
			}
		}
		
		if ( $problems ){
			if ( $fatal ){
				$message = 'Requested an unavailable or non-existent form.';
				$this->logger->error( $message . ' ' . $debug_message . ' ' . $this->gateway->getData_Unstaged_Escaped('utm_source') );
				throw new RuntimeException( $message );
			} else {
				return;
			}
		}

		if ( array_key_exists( 'special_type', $allowedForms[$form_key] ) ) {
			if ( $allowedForms[$form_key]['special_type'] === 'error' ) {
				//add data we're going to need for the error page!
				$back_form = $this->gateway->session_getLastFormName();

				//TODO: What to do if $back_form doesn't exist, because session expire
				//TODO: Also, what to do if they just have... no required data.

				$params = array (
					'gateway' => $this->gateway->getIdentifier()
				);
				if ( !$this->gateway->session_hasDonorData() ) {
					$preserve = $this->gateway->getRetryData();
					$params = array_merge( $preserve, $params );
				}
				//If this is just the one thing, we might move this inside DonationData for clarity's sake...
				$this->gateway->addRequestData( array ( 'ffname_retry' => GatewayFormChooser::buildPaymentsFormURL( $back_form, $params ) ) );
			}
		} else {
			//No special type... let's add this to the form stack and call it good.
			$this->gateway->session_pushFormName( $form_key );
		}

		$this->html_file_path = $allowedForms[$form_key]['file'];
	}

	/**
	 * Gets a list of the supported countries from the parent class
	 * and returns an option list representing all of those countries
	 * in a translatable fashion.
	 *
	 * @return string An option list containing all supported countries
	 */
	function getCountryDropdown() {
		global $wgDonationInterfaceForbiddenCountries;

		//returns an array of iso_code => country name
		$countries = GatewayPage::getCountries();

		//unset blacklisted countries first
		foreach ( $wgDonationInterfaceForbiddenCountries as $country_code ) {
			unset( $countries[$country_code] );
		}
		//only use countries from that array that are represented in the form definition
		foreach ( $countries as $code => $name ) {
			if ( !GatewayFormChooser::isSupportedCountry( $code, $this->gateway->getData_Unstaged_Escaped( 'ffname' ) ) ) {
				unset( $countries[$code] );
			}
		}

		$output = "";

		# iterate through the countris, ignoring the value since we
		# will generate a message key to replace later
		foreach( $countries as $c => $v ) {
			$output .= "<option value=\"" . $c . "\">%donate_interface-country-dropdown-" . $c . "%</option>\n";
		}

		return $output;
	}

}
