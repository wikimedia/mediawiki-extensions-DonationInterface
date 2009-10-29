<?php
/**
 * DonateInterface extension
 *
 * @file
 * @ingroup Extensions
 * @link http://www.mediawiki.org/wiki/Extension:DonateInterface Documentation
 */

if( !defined( 'MEDIAWIKI' ) ) {
	die( "This is not a valid entry point.\n" );
}

// Extension credits that will show up on Special:Version

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'DonateInterface',
	//'author' => array( 'diana' ), // FIXME: Committer does not have details in http://svn.wikimedia.org/viewvc/mediawiki/USERINFO/
	'description' => 'Donate interface',
	'descriptionmsg' => 'donate_interface-desc', 
	'url' => 'http://www.mediawiki.org/wiki/Extension:DonateInterface',
);

// Set up i18n
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['DonateInterface'] = $dir . 'donate_interface.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'efDonateSetup';
$wgHooks['MediaWikiPerformAction'][] = 'fnProcessDonateForm';

/**
 * Create <donate /> tag to include landing page donation form
 */
function efDonateSetup( &$parser ) {
  global $wgHooks;
	
  //load extension messages
  wfLoadExtensionMessages( 'donate_interface' );

  $parser->disableCache();

  $parser->setHook( 'donate', 'efDonateRender' );
	       
	 //process form
	 wfRunHooks( 'MediaWikiPerformAction', array( $output, 
$article, $title, $user, $request, $this));

  return true;
}

/**
 * Function called by the <donate> parser tag
 *
 * Outputs the donation landing page form which collects
 * the donation amount, currency and payment processor type.
 */
function efDonateRender( $input, $args, &$parser ) {
	global $wgOut, $wgScriptPath;
  
  $parser->disableCache();
        
  // if chapter exists for user's country, redirect
  $chapter = fnDonateChapterRedirect();
        
  // add JavaScript validation to <head>
  $wgOut->addScriptFile( $wgScriptPath . '/extensions/DonationInterface/donate_interface/donate_interface_validate_donation.js' );
 
        
  //display form to gather data from user
  $output = fnDonateCreateOutput();
              
  return $output;
}

/**
 * Supplies the form to efDonateRender()
 *
 * Payment gateway options are created with the hook "gwValue". Each potential payment
 * option supplies it's value and name for the form, as well as currencies it supports.
 */
function fnDonateCreateOutput() {
  global $wgOut, $wgRequest;

  // declare variable
	$utm_source = '';
	$utm_medium = '';
	$utm_campaign = '';
	$referrer = '';
	
  // set them equal to post data
  $utm_source = $wgRequest->getText( 'utm_source' );
  $utm_medium = $wgRequest->getText( 'utm_medium' );
  $utm_campaign = $wgRequest->getText( 'utm_campaign' );
  $referrer = $_SERVER['HTTP_REFERER'];
        
  //get language from URL
  $url = $_SERVER['REQUEST_URI'];
        
  if ($url) {
    $getLang = explode('/', $url);
    $language = substr($getLang[3], 0, 2);
  }
        
  // error check and set "en" as default
  if ( !preg_match( '/^[a-z-]+$/', $language ) ) {
    $language = 'en';
  }

  //get payment method gateway value and name from each gateway and create menu of options
  $values = '';
  wfRunHooks('DonationInterface_Value', array(&$values)); 
	
  $gatewayMenu = '';
  
	foreach($values as $current) {
    $gatewayMenu .= Xml::option($current['display_name'], $current['form_value']);
    }
  
    //get available currencies
    foreach($values as $key) {
      $currencies = $key['currencies'];
    }

	$currencyMenu = '';

  foreach( $currencies as $value => $fullName ) {
    $currencyMenu .= Xml::option( $fullName, $value );
  }
    
  $output = Xml::openElement( 'form', array( 'name' => "donate", 'method' => "post", 'action' => "", 'onsubmit' => 'return DonateValidateForm(this)' )) .
        Xml::openElement( 'div', array('id' => 'mw-donation-intro' )) .
        Xml::element( 'p', array( 'class' => 'mw-donation-intro-text' ), wfMsg( 'donate_interface-intro' )) .
        Xml::closeElement( 'div' );
                
  $output .= Xml::hidden( 'utm_source', $utm_source ) .
        Xml::hidden( 'utm_medium', $utm_medium ) . 
        Xml::hidden( 'utm_campaign', $utm_campaign ) .
        Xml::hidden( 'language', $language ) .
        Xml::hidden( 'referrer', $referrer );
        
  $amount = array(
        Xml::radioLabel(wfMsg( 'donate_interface-big-amount-display' ), 'amount', wfMsg( 'donate_interface-big-amount-value' ), 'input_amount_3', false  ),
        Xml::radioLabel(wfMsg( 'donate_interface-medium-amount-display' ), 'amount', wfMsg( 'donate_interface-medium-amount-value' ), 'input_amount_2', false ),
        Xml::radioLabel(wfMsg( 'donate_interface-small-amount-display' ), 'amount', wfMsg( 'donate_interface-small-amount-value' ), 'input_amount_1', false ),
        Xml::inputLabel(wfMsg( 'donate_interface-other-amount' ), 'amount2', 'input_amount_other', '5'),
  );
        
  $amountFields = '<table><tr>';
    foreach( $amount as $value ) {
      $amountFields .= '<td>' . $value . '</td>';
    }
    $amountFields .= '</tr></table>';
        
  $output .= Xml::fieldset(wfMsg( 'donate_interface-amount' ), $amountFields,  array('class' => "mw-donation-amount"));
        
  // Build currency options
  $default_currency = fnDonateDefaultCurrency();
        
  $currency_options = '';
    foreach ( $currencies as $code => $name ) {
      $selected = '';
        if ( $code == $default_currency ) {
          $selected = ' selected="selected"';
        }
      $currency_options .= '<option value="' . $code . '"' . $selected . '>' . $name . '</option>';
    }
      
  $currencyFields = Xml::openElement( 'select', array( 'name' => 'currency_code', 'id' => "input_currency_code" )) .
              $currency_options . 
              Xml::closeElement( 'select' );
        
  $output .= Xml::fieldset(wfMsg( 'donate_interface-currency' ), $currencyFields,  array('class' => "mw-donation-currency" ));
        
  $gatewayFields = Xml::openElement( 'select', array('name' => 'payment_method', 'id' => 'select_payment_method')) . 
              $gatewayMenu .
              Xml::closeElement('select');
        
  $output .= Xml::fieldset(wfMsg( 'donate_interface-gateway' ), $gatewayFields,  array( 'class' => 'mw-donation-gateway' ));
        
  $publicComment = Xml::element( 'div', array( 'class' => 'mw-donation-comment-message'), wfMsg( 'donate_interface-comment-message' )) . 
        Xml::inputLabel(wfMsg( 'donate_interface-comment-label' ), 'comment', 'comment', '30', '', array( 'maxlength' => '200' )) .
        Xml::openElement( 'div', array( 'id' => 'mw-donation-checkbox' )) .
        Xml::checkLabel( wfMsg( 'donate_interface-anon-message' ), 'comment-option', 'input_comment-option', TRUE ) . 
        Xml::closeElement( 'div' ) .
        Xml::openElement( 'div', array( 'id' => 'mw-donation-checkbox' )) .
        Xml::check( 'opt', TRUE ) .
        Xml::tags( 'span', array( 'class' => 'mw-email-agreement' ), wfMsg( 'donate_interface-email-agreement' )) .
        Xml::closeElement( 'div' );
        
  $output .= Xml::fieldset(wfMsg( 'donate_interface-comment-title' ), $publicComment, array( 'class' => 'mw-donation-public-comment'));
                
  $output .= Xml::hidden( 'process', '_yes_' ) .
        Xml::submitButton(wfMsg( 'donate_interface-submit-button' ));
       
        $output .= Xml::closeElement( 'form' );
                
  // NOTE: For testing: show country of origin
  //$country = fnDonateGetCountry();
  //$output .= Xml::element('p', array('class' => 'mw-donation-test-message'), 'Country:' . $country);
        
  // NOTE: for testing: show default currency
  //$currencyTest = fnDonateDefaultCurrency();
  //$output .= Xml::element('p', array('class' => 'mw-donation-test-message'), wfMsg( 'donate_interface-currency' ) . $currencyTest);
        
  // NOTE: for testing: show IP address
  //$referrer = $_SERVER['HTTP_REFERER'];
  //$output .= '<p>' . 'Referrer:' . $referrer . '</p>';
        
  //for testing to show language culled from URL
  $output .= '<p>' . ' Language: ' . $language . '</p>';
              
  return $output;
}

/*
* Redirects user to their chosen payment processor
* 
* Includes the user's input passed as GET
* $url for the gateway was supplied with the gwPage hook and the key
* matches the form value (also supplied by the gateway)
*/
function fnDonateRedirectToProcessorPage($userInput, $url) {
  global $wgOut,$wgPaymentGatewayHost;
        
  $chosenGateway = $userInput['payment_method'];

	$wgOut->redirect(
		$wgPaymentGatewayHost . $url[$chosenGateway] . '&amount=' . 
		$userInput['amount'] . '&currency_code=' . $userInput['currency'] .
	       	'&gateway=' . $userInput['payment_method'] . '&referrer=' . 
		$userInput['referrer'] . '&utm_source=' . $userInput['utm_source'] .
	       	'&utm_medium=' . $userInput['utm_medium'] . '&utm_campaign=' . 
		$userInput['utm_campaign'] .'&language=' . $userInput['language'] . 
		'&comment=' . $userInput['comment'] . '&comment-option=' . 
		$userInput['comment-option'] .'&email=' . $userInput['email']
	);
}

/**
 * Gets country code based on IP if GeoIP extension is installed
 * returns country code or UNKNOWN if unable to assign one
 */
function fnDonateGetCountry() {
	$country_code = null;

	if( function_exists( 'fnGetGeoIP' ) ) {
		try {
			$country_code = fnGetGeoIP();
		} catch ( NotFoundGeoIP $e ) {
			$country_code = 'UNKNOWN';
		} catch ( UnsupportedGeoIP $e ) {
			$country_code = 'UNKNOWN';
		}
	}

	return $country_code;
}

/**
 * Uses GeoIP extension to translate country based on IP
 * into default currency shown in drop down menu
 */
function fnDonateDefaultCurrency() {
	require_once( 'country2currency.inc' );

	$country_code = null;
	$currency = null;

	if( function_exists( 'fnGetCountry' ) ) {
		$country_code = fnGetCountry();
	}

	$currency = fnCountry2Currency( $country_code );

	return $result = $currency ? $currency : 'USD';
}

/**
 * Will use GeoIP extension to redirect user to 
 * chapter page as dictated by IP address
 * NOT CURRENTLY IN USE
 */
function fnDonateChapterRedirect() {
	require_once( 'chapters.inc' );
  
  $country_code = null;

	if( function_exists( 'fnGetCountry' ) ) {
		$country_code = fnDonateGetCountry();
	}

	$chapter = fnDonateGetChapter( $country_code );

	if( $chapter ) {
		global $wgOut;
		$wgOut->redirect( 'http://' . $chapter );
	} else {
		return null;
	}

}
  
function fnProcessDonateForm( $output, $article, $title, $user, $request, $wiki ) {
  global $wgRequest, $wgOut;
    
  // declare variables used to hold post data
  $userInput = array (
      'currency' => 'USD',
      'amount' => '0.00',
      'payment_method' => '',
      'referrer' => '',
      'utm_source' => '',
      'utm_medium' => '',
      'utm_campaign' => '',
      'language' => '',
      'comment' => '',
      'comment-option' => '',
      'email' => '',
	 );
	
	// if form has been submitted, assign data and redirect user to chosen payment gateway
	if ( isset($_POST['process']) && $_POST['process'] == "_yes_" ) { 
    //find out which amount option was chosen for amount, redefined buttons or text box
    if ( isset($_POST['amount']) && preg_match('/^\d+(\.(\d+)?)?$/', $wgRequest->getText('amount')) ) {
		  $amount = number_format( $wgRequest->getText('amount'), 2 );
    } elseif ( preg_match('/^\d+(\.(\d+)?)?$/', $wgRequest->getText('amount2') )) { 
        $amount = number_format( $wgRequest->getText('amount2'), 2, '.', '' ); 
    } else {
        $wgOut->addHTML( wfMsg( 'donate_interface-amount-error' ) );
        return true;
    }	 
	 
  // create	array of user input from post data
  $userInput = array (
        'currency' => $wgRequest->getText( 'currency_code', 'USD' ),
        'amount' => $amount,
        'payment_method' => $wgRequest->getText( 'payment_method', 'payflow' ),
        'referrer' => $wgRequest->getText( 'referrer', '' ),
        'utm_source' => $wgRequest->getText( 'utm_source', '' ),
        'utm_medium' => $wgRequest->getText( 'utm_medium', '' ),
        'utm_campaign' => $wgRequest->getText( 'utm_campaign', '' ),
        'language' => $wgRequest->getText( 'language', 'en' ),
        'comment' => $wgRequest->getText( 'comment', '' ),
        'comment-option' => $wgRequest->getText( 'comment-option', '1' ),
        'email' => $wgRequest->getText( 'opt', '1' ),
  );
	 
  // ask payment processor extensions for their URL/page title
  $url = '';
    
  if ( wfRunHooks('DonationInterface_Page', array(&$url)) ) {
	
	   // send user to correct page for payment  
    fnDonateRedirectToProcessorPage( $userInput, $url );
    
  } else {
      $wgOut->addHTML( wfMsg( 'donate_interface-processing-error' ) );
    }
  
  }// end if form has been submitted
    
  return true;
}

