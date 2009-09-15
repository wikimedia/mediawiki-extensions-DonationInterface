<?php
//Avoid unstubbing $wgParser on setHook() too early on modern (1.12+) MW versions, as per r35980
if ( defined( 'MW_SUPPORTS_PARSERFIRSTCALLINIT' ) ) {
	$wgHooks['ParserFirstCallInit'][] = 'efDonateSetup';
} else { // Otherwise do things the old fashioned way
	$wgExtensionFunctions[] = 'efDonateSetup';
}

$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['donate_interface'] = $dir . 'donate_interface.i18n.php';

/*
* Create <donate /> tag to include landing page donation form
*/
function efDonateSetup(&$parser) {
	global $wgParser, $wgOut, $wgRequest;
	
	//load extension messages
	wfLoadExtensionMessages( 'donate_interface' );
	
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
		  $gatewayMenu .= XML::option($current['display_name'], $current['form_value']);
        }
  
        //get available currencies
        //NOTE: currently all available currencies are accepted by all developed gateways
        //the next version will include gateway/currency checking 
        //and inform user of gateways that aren't an option for that currency
        foreach($values as $key) {
                $currencies = $key['currencies'];
        }
 
        foreach($currencies as $value => $fullName) {
                $currencyMenu .= XML::option($fullName, $value);
        }
    
        $output = XML::openElement('form', array('name' => "donate", 'method' => "post", 'action' => "", 'onsubmit' => 'return validateForm(this)')) .
                XML::openElement('div', array('id' => 'mw-donation-intro')) .
                XML::element('p', array('class' => 'mw-donation-intro-text'), wfMsg('donation-intro')) .
                XML::closeElement('div');
        
        $amount = array(
                XML::radioLabel('$100', 'amount', '100', 'input_amount_3', FALSE, array("")),
                XML::radioLabel('$75', 'amount', '75', 'input_amount_2', FALSE, array("")),
                XML::radioLabel('$30', 'amount', '30', 'input_amount_1', FALSE, array("")),
                XML::inputLabel('Other Amount: ', "amount2", "input_amount_other", "5"),
        );
        
        $amountFields = "<table><tr>";
                foreach($amount as $value) {
                        $amountFields .= '<td>' . $value . '</td>';
                }
        $amountFields .= '</tr></table>';
        
        $output .= XML::fieldset(wfMsg( 'donor-amount' ), $amountFields,  array('class' => "mw-donation-amount"));
        
        $currencyFields = XML::openElement('select', array('name' => "currency_code", 'id' => "input_currency_code")) .
                '<option value="USD" selected="selected">USD: U.S. Dollar</option>' . 
                '<option value="XXX">-------</option>' . 
                $currencyMenu .
                XML::closeElement('select');
        
        $output .= XML::fieldset(wfMsg( 'donor-currency' ), $currencyFields,  array('class' => "mw-donation-currency"));
        
        $gatewayFields = XML::openElement('select', array('name' => "payment_method", 'id' => "select_payment_method")) . 
                $gatewayMenu .
                XML::closeElement('select');
        
        $output .= XML::fieldset(wfMsg( 'donor-gateway' ), $gatewayFields,  array('class' => "mw-donation-gateway")) .
                XML::hidden('process', '_yes_') .
                XML::submitButton("Donate");
        
    
        $output .= XML::closeElement('form');
    
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




