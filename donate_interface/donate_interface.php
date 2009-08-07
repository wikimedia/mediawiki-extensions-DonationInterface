<?php
//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'efDonateSetup';
} else { // Otherwise do things the old fashioned way
	$wgExtensionFunctions[] = 'efDonateSetup';
}

/*
* Create <donate /> tag to include landing page donation form
*/
function efDonateSetup(&$parser) {
	global $wgParser, $wgOut, $wgRequest;
	
	$parser->disableCache();
				
	$wgParser->setHook( 'donate', 'efDonateRender' );
	
	
	// if form has been submitted, assign data and redirect user to chosen payment gateway
	if ($_POST['process'] == "_yes_") { 
	 //find out which amount option was chosen for amount, redefined buttons or text box
	 if (isset($_POST['amount'])) {
		  $amount = number_format($wgRequest->getText('amount'), 2);
		} else { $amount = number_format($wgRequest->getText('amount2'), 2, '.', ''); }	 
	 
	 // create	array of user input
	 $userInput = array (
	   'currency' => $wgRequest->getText('currency_code'),
	   'amount' => $amount,
	   'payment_method' => $wgRequest->getText('payment_method')
	 );
	 
  // ask payment processor extensions for their URL/page title
  wfRunHooks('gwPage', array(&$url));
	
	// send user to correct page for payment  
  redirectToProcessorPage($userInput, $url);
  
  }// end if form has been submitted
  
  return true;
}


/*
* Function called by the <donate> parser tag
*
* Outputs the donation landing page form which collects
* the donation amount, currency and payment processor type.
*   
*/
function efDonateRender( $input, $args, &$parser) {
  global $wgOut;
  
	 $parser->disableCache();
    
    //add javascript validation to <head>
    $parser->mOutput->addHeadItem('<script type="text/javascript" language="javascript" src="/extensions/donate_interface/validate_donation.js"></script>');
    
    //display form to gather data from user
    $output = createOutput();
      
  return $output;
}

/*
* Supplies the form to efDonateRender()
*
* Payment gateway options are created with the hook 'gwValue". Each potential payment
* option supplies it's value and name for the form, as well as currencies it supports.  
*/
function createOutput() {

  //get payment method gateway value and name from each gateway and create menu of options
  $values = '';
  wfRunHooks('gwValue', array(&$values)); 
  
	foreach($values as $current) {
		  $optionMenu .= '<option value='. $current['form_value'] . '>'. $current['display_name'] .'</option>';
  }
  
  //get available currencies
  //NOTE: currently all available currencies are accepted by all developed gateways
  //the next version will include gateway/currency checking 
  //and inform user of gateways that aren't an option for that currency
  foreach($values as $key) {
    $currencies = $key['currencies'];
  }
 
  foreach($currencies as $key => $value) {
    $currencyMenu .= '<option value='. $key .'>'. $value .'</option>';
  }
	
	// form markup
  $output ='<form method="post"  action="" name="donate" onsubmit="return validateForm(this)">
    <div>Please choose a payment method, amount, and currency. (Other ways to give, including check or mail, can be <a href="/wiki/Donate/WaysToGive/en" title="Donate/WaysToGive/en">found here</a>.)</div>
    <div>Donation Amount: </div>
    <div id="amount_box">
     <input type="hidden" name="title" value="Special:PayflowPro" />
    <input type="radio" name="amount" id="input_amount_3" value="100" />
    <label for="input_amount_3">$100</label> 
    <input type="radio" name="amount" id="input_amount_2"  value="75" />
    <label for="input_amount_2">$75</label>
    <input type="radio" name="amount" id="input_amount_1" value="30" />
    <label for="input_amount_1">$30</label>
    <label for="input_amount_other">Other: </label> <input type="text" name="amount2" size="5"  />
    <!-- currency menu -->
    <p>Select Currency</p>
    <select name="currency_code" id="input_currency_code" size="1">
    <option value="USD" selected="selected">USD: U.S. Dollar</option>
    <option value="XXX">-------</option>
    '. $currencyMenu .'
    </select></div>
    <div>Payment method:
    <select name="payment_method" id="select_payment_method">
    '.$optionMenu.'
    </select></div>
    <input type="hidden" name="process" value="_yes_" />
    <br />
    <input class="red-input-button" type="submit" value="DONATE"/>
    </form>';
    
    return $output;
}


/*
* Redirects user to their chosen payment processor
* 
* Includes the user's input passed as GET
* $url for the gateway was supplied with the gwPage hook and the key
* matches the form value (also supplied by the gateway)
*/
function redirectToProcessorPage($userInput, $url) {
  global $wgOut;
  
  $chosenGateway = $userInput['payment_method'];

	 $wgOut->redirect($url[$chosenGateway].'&amount='.$userInput['amount'].'&currency_code='.$userInput['currency']);

}




