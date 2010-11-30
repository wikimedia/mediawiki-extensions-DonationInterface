<?php

class PayflowProGateway_Form_RapidHtml extends PayflowProGateway_Form {
	
	/**
	 * Full path of HTML form to load
	 * @var string
	 */
	protected $html_file_path = '';
	
	/**
	 * Tokens used in HTML form for data replacement
	 * 
	 * Note that these NEED to be in the same order as the variables in $data in 
	 * order for str_replace to work as expected
	 * @var array
	 */
	protected $data_tokens = array(
		'@amount', // => $amount,
		'@amountOther', // => $wgRequest->getText( 'amountOther' ),
		'@emailAdd', //'email' => $wgRequest->getText( 'emailAdd' ),
		'@fname', // => $wgRequest->getText( 'fname' ),
		'@mname', // => $wgRequest->getText( 'mname' ),
		'@lname', // => $wgRequest->getText( 'lname' ),
		'@street', // => $wgRequest->getText( 'street' ),
		'@city', // => $wgRequest->getText( 'city' ),
		'@state', // => $wgRequest->getText( 'state' ),
		'@zip', // => $wgRequest->getText( 'zip' ),
		'@country', // => $wgRequest->getText( 'country' ),
		'@card_num', // => str_replace( ' ', '', $wgRequest->getText( 'card_num' ) ),
		'@card', // => $wgRequest->getText( 'card' ),
		'@expiration', // => $wgRequest->getText( 'mos' ) . substr( $wgRequest->getText( 'year' ), 2, 2 ),
		'@cvv', // => $wgRequest->getText( 'cvv' ),
		'@currency_code', //'currency' => $wgRequest->getText( 'currency_code' ),
		'@payment_method', // => $wgRequest->getText( 'payment_method' ),
		'@orderid', // => $order_id,
		'@numAttempt', // => $numAttempt,
		'@referrer', // => ( $wgRequest->getVal( 'referrer' ) ) ? $wgRequest->getVal( 'referrer' ) : $wgRequest->getHeader( 'referer' ),
		'@utm_source', // => self::getUtmSource(),
		'@utm_medium', // => $wgRequest->getText( 'utm_medium' ),
		'@utm_campaign', // => $wgRequest->getText( 'utm_campaign' ),
		// try to honr the user-set language (uselang), otherwise the language set in the URL (language)
		'@language', // => $wgRequest->getText( 'uselang', $wgRequest->getText( 'language' ) ),
		'@comment-option', // => $wgRequest->getText( 'comment-option' ),
		'@comment', // => $wgRequest->getText( 'comment' ),
		'@email-opt', // => $wgRequest->getText( 'email-opt' ),
		'@test_string', // => $wgRequest->getText( 'process' ), // for showing payflow string during testing
		'@token', // => $token,
		'@contribution_tracking_id', // => $wgRequest->getText( 'contribution_tracking_id' ),
		'@data_hash', // => $wgRequest->getText( 'data_hash' ),
		'@action', // => $wgRequest->getText( 'action' ),
		'@gateway', // => 'payflowpro', // this may need to become dynamic in the future
		'@owa_session', // => $wgRequest->getText( 'owa_session', null ),
		'@owa_ref', // => $owa_ref,
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
		'#card',
		'#cvv',
		'#fname',
		'#lname',
		'#city',
		'#country',
		'#street',
		'#state',
		'#zip',
		'#emailAdd',
	);
	
	public function __construct( &$form_data, &$form_errors ) {
		global $wgRequest;

		parent::__construct( $form_data, $form_errors );
		
		$this->loadValidateJs();
		
		// set html-escaped filename.
		$this->set_html_file_path( htmlspecialchars( $wgRequest->getText( 'ffname', 'default' )));
		
		// fix general form error messages so it's not an array of msgs
		if ( count( $form_errors[ 'general' ] )) {
			$general_errors = "";
			foreach ( $form_errors[ 'general' ] as $general_error ) {
				$general_errors .= "<p class='creditcard'>$general_error</p>";
			}
			$form_errors[ 'general' ] = $general_errors;
		}
	}
	
	/**
	 * Return the HTML form with data added
	 */
	public function getForm() {
		$html = $this->load_html();
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
		// replace data
		$form = str_replace( $this->data_tokens, $this->form_data, $html );

		// replace errors
		$form = str_replace( $this->error_tokens, $this->form_errors, $form );

		// handle captcha
		$form = str_replace( "@captcha", $this->getCaptchaHtml(), $form );
		
		$form = $this->fix_dropdowns( $form );
		
		return $form;
	}

	/**
	 * Set dropdowns to 'selected' where appropriate
	 * 
	 * This is basically a hackish fix to make sure that dropdowns stay 
	 * 'sticky' on form submit.  This could no doubt be better.
	 * @param $html
	 * @return string
	 */
	public function fix_dropdowns( $html ) {
		// currency code
		$start = strpos( $html, 'name="currency_code"' );
		if ( $start ) {
			$currency_code = $this->form_data[ 'currency' ];
			$end = strpos( $html, '</select>', $start );
			$str = substr( $html, $start, ( $end - $start ));
			$str = str_replace( 'value="' . $currency_code . '"', 'value="' . $currency_code . '" selected="selected"', $str );
			$html = substr_replace( $html, $str, $start, $end-$start );
		}
		
		// mos
		$month = substr( $this->form_data[ 'expiration' ], 0, 2 );
		$start = strpos( $html, 'name="mos"' );
		if ( $start ) {
			$end = strpos( $html, '</select>', $start );
			$str = substr( $html, $start, ( $end - $start ));
			$str = str_replace( 'value="' . $month . '"', 'value="' . $month . '" selected="selected"', $str );
			$html = substr_replace( $html, $str, $start, $end-$start );
		}
		
		// year
		$year = substr( $this->form_data[ 'expiration' ], 2, 2 );
		$start = strpos( $html, 'name="year"' );
		if ( $start ) {	
			$end = strpos( $html, '</select>', $start );
			$str = substr( $html, $start, ( $end - $start ));
			// dbl extra huge hack alert!  note the '20' prefix...
			$str = str_replace( 'value="20' . $year . '"', 'value="20' . $year . '" selected="selected"', $str );
			$html = substr_replace( $html, $str, $start, $end-$start );
		}
		
		// state
		$state = $this->form_data[ 'state' ];
		$start = strpos( $html, 'name="state"' );
		if ( $start ) {
			$end = strpos( $html, '</select>', $start );
			$str = substr( $html, $start, ( $end - $start ));
			$str = str_replace( 'value="' . $state . '"', 'value="' . $state . '" selected="selected"', $str );
			$html = substr_replace( $html, $str, $start, $end-$start );
		}
		
		//country
		$country = $this->form_data[ 'country' ];
		$start = strpos( $html, 'name="country"' );
		if ( $start ) {
			$end = strpos( $html, '</select>', $start );
			$str = substr( $html, $start, ( $end - $start ));
			$str = str_replace( 'value="' . $country . '"', 'value="' . $country . '" selected="selected"', $str );
			$html = substr_replace( $html, $str, $start, $end-$start );
		}
		
		return $html;
	}
	
	/**
	 * Validate and set the path to the HTML file
	 * 
	 * @param string $file_name
	 */
	public function set_html_file_path( $file_name ) {
		global $wgPayflowHtmlFormDir, $wgPayflowAllowedHtmlForms;

		// Get the dirname - the "/." helps ensure we get a consistent path name with no trailing slash
		$html_dir = dirname( $wgPayflowHtmlFormDir . "/." );
		
		if ( !is_dir( $html_dir )) {
			throw new MWException( 'Requested form directory does not exist.' );
		}
		
		// make sure our file name is clean - strip extension and any other cruft like relpaths, dirs, etc
		$file_info = pathinfo( $file_name );
		$file_name = $file_info[ 'filename' ];
		
		$full_path = $html_dir . '/' . $file_name . '.html';
		
		// ensure that the full file path is actually whitelisted and exists
		if ( !in_array( $full_path, $wgPayflowAllowedHtmlForms ) || !file_exists( $full_path ) ) {
			throw new MWException( 'Requested an unavailable or non-existent form.' );
		}
		
		$this->html_file_path = $full_path;
	}
}