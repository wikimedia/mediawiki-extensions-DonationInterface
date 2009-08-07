<?php   
class PayflowProGateway extends SpecialPage {
	function __construct() {
		parent::__construct( 'PayflowProGateway', $restriction = '', $listed = false );
		
		wfLoadExtensionMessages('PayflowProGateway');
	}
 
  function execute( $par ) {
    global $wgRequest, $wgOut, $wgUser;
    
    global $wgParser;
	  $wgParser->disableCache();

  
		$this->setHeaders();
		
		//create token if one doesn't already exist
		$token = $wgUser->editToken('mrxc877668DwQQ');
		
		// Declare form post variables 
		$data = array(
		  'amount' => '',
		  'email' => '',
		  'fname' => '',
		  'mname' => '',
		  'lname' => '',
		  'street' => '',
		  'city' => '',
		  'state' => '',
		  'zip' => '',
		  'country' => '',
		  'card_num' => '',
		  'expiration' => '',
		  'cvv' => '',
		  'currency' => '',
		  'payment_method' => '',
		);
		  
		$error[] = '';
		
		//find out if amount was a radio button or textbox, set amount
		if (isset($_REQUEST['amount'])) {
		  $amount = number_format($wgRequest->getText('amount'), 2);
		} else if (isset($_REQUEST['amount2'])) { 
		    $amount = number_format($wgRequest->getText('amount2'), 2, '.', ''); 
		} else { 
		    $wgOut->addHTML("This page is only accessible from the donation page"); 
		    return;
		}
 
		// Populate from data  
		$data = array(  
		  'amount' => $amount,
		  'email' => $wgRequest->getText('email'),
		  'fname' => $wgRequest->getText('fname'),
		  'mname' => $wgRequest->getText('mname'),
		  'lname' => $wgRequest->getText('lname'),
		  'street' => $wgRequest->getText('street'),
		  'city' => $wgRequest->getText('city'),
		  'state' => $wgRequest->getText('state'),
		  'zip' => $wgRequest->getText('zip'),
		  'country' => $wgRequest->getText('country'),
		  'card' => $wgRequest->getText('card'),
		  'card_num' => str_replace(' ','',$wgRequest->getText('card_num')),
		  'expiration' => $wgRequest->getText('mos').substr($wgRequest->getText('year'), 2, 2),
		  'cvv' => $wgRequest->getText('cvv'),
		  'currency' => $wgRequest->getText('currency_code'),
		  'payment_method' => $wgRequest->getText('payment_method'),
		  'token' => $wgRequest->getVal('token'),
		  'test_string' => $wgRequest->getText('process') //for showing payflow string during testing
		);
		
		
		// Get array of default account values necessary for Payflow 
		require_once('includes/payflowUser.inc');
		
		$payflow_data = payflowUser();
		
    // Check form for errors and display 
    // match token
    $success = $wgUser->matchEditToken($token, 'mrxc877668DwQQ');
		
		if ($success) {
      if ($data['payment_method'] == "processed") {
        // Check form for errors and redisplay with messages
        if ($form_errors = $this->validateForm($data, $error)) {
          $this->displayForm($data, $error);
        } else {
            // The submitted data is valid, so process it
            $this->processTransaction($data, $payflow_data);
        }
      } 
      else {
        //Display form for the first time
        $this->displayForm($data, $error);
      }
  } // end $success
    
}
 
  /* 
   * Displays form to user
   * 
   * @params  
   * $data array of posted user input
   * $error array of error messages returned by validate_form function
   *
   * Form can be output as a table or as a fieldset.  Fieldset is commented out by default.  Both include class names.
   *
   * The message at the top of the form can be edited in the payflow_gateway.i18.php file 
   */
  private function displayForm($data, &$error) {
    require_once('includes/stateAbbreviations.inc');
		require_once('includes/countryCodes.inc');
		
		global $wgOut;		
		
		$form = wfMsg('form-message');
		
		//show error messages if they exist
		if (!empty($error)) {
		  //add styling
		  $form .= '<div class="creditcard_error">';
		  foreach($error as $key) {
		    $form .= '<p class="creditcard_error_msg">'.$key.'</p>';
		  }
		  $form .= '</div>';
		}
		
		//create drop down of countries
		//$countries = $this->countryCodes();
		$countries = countryCodes();
		
		foreach($countries as $key => $value) {
		  $countryMenu .= '<option value='. $key . '>'. $value .'</option>';
		}
		
    //Form implemented as a table with classnames
    //***Comment this section out and uncomment the subsequent implementation as a fieldset if preferred***
    $form .='<div class="mw-creditcard_form"><form name="payment" method="post" action="">
      <table class="mw-creditcard_table" border="0" cellspacing="1" cellpadding="3">
      <tr><td class="mw-mw-creditcard-label">Donation Amount: </td><td class="mw-mw-creditcard-input">'.$data['amount'].'</td></tr>
        <input type="hidden" name="amount" value="'.$data['amount'].'">
      <tr><td class="mw-creditcard-label">Donor Information:</td></tr>
      <tr><td class="mw-creditcard-name">E-mail Address:</td>
        <td class="mw-creditcard-input"><input type="text" name="email" value="'.$data['email'].'" maxlength="150" size="30" /></td></tr>
      <tr><td class="mw-creditcard-name">First Name:</td>
        <td class="mw-creditcard-input"><input type="text" name="fname" value="'.$data['fname'].'" maxlength="35" size="20" /></td></tr>
      <tr><td class="mw-creditcard-name">Middle Name:</td>
        <td class="mw-creditcard-input"><input type="text" name="mname" value="'.$data['mname'].'" maxlength="35" size="20" /></td></tr>
      <tr><td class="mw-creditcard-name">Last Name:</td>
        <td class="mw-creditcard-input"><input type="text" name="lname" value="'.$data['lname'].'" maxlength="35" size="20" /></td></tr>
      <tr><td class="mw-creditcard-name">Street:</td>
        <td class="mw-creditcard-input"><input type="text" name="street" value="'.$data['street'].'" maxlength="100" size="30" /></td></tr>
      <tr><td class="mw-creditcard-name">City:</td>
        <td class="mw-creditcard-input"><input type="text" name="city" value="'.$data['city'].'" maxlength="35" size="20" /></td></tr>
      <tr><td class="mw-creditcard-name">State:</td>
        <td class="mw-creditcard-input"><select name="state" value="'.$data['state'].'" />'. statesMenu() .'</td></tr>
      <tr><td class="mw-creditcard-name">Postal Code:</td>
        <td class="mw-creditcard-input"><input type="text" name="zip" value="'.$data['zip'].'" maxlength="18" size="15" /></td></tr>
      <tr><td class="mw-creditcard-name">Country/Region:</td>
        <td class="mw-creditcard-input"><select name="country" value="'.$data['country'].'" />'.$countryMenu.'</select></td></tr>
      <tr><td class="mw-creditcard-label">Credit Card Information:</td></tr>
      <tr><td class="mw-creditcard-name">Credit Card:</td>
        <td class="mw-creditcard-input"><select name="card">
        <option value="visa">Visa</option><option selected="selected" value="mastercard">MasterCard</option>
        <option value="american">American Express</option></select></td></tr>
      <tr><td class="mw-creditcard-name">Card Number: </td>
        <td class="mw-creditcard-input"><input type="text" name="card_num"  maxlength="100" /></td></tr>
      <tr><td class="mw-creditcard-name">Expiration Date:</td><td class="mw-creditcard-input">
        <select name="mos">
        <option value="01">01</option>
        <option value="02">02</option>
        <option value="03">03</option>
        <option value="04">04</option>
        <option value="05">05</option>
        <option value="06">06</option>
        <option value="07">07</option>
        <option value="08">08</option>
        <option value="09">09</option>
        <option value="10">10</option>
        <option value="11">11</option>
        <option value="12" selected="selected">12</option>
        </select>
      <select name="year">
        <option value="'.(date('Y')).'">'.(date('Y')).'</option>
        <option value="'.(date('Y')+1).'">'.(date('Y')+1).'</option>
        <option value="'.(date('Y')+2).'" selected="selected">'.(date('Y')+2).'</option>
        <option value="'.(date('Y')+3).'">'.(date('Y')+3).'</option>
        <option value="'.(date('Y')+4).'">'.(date('Y')+4).'</option>
        <option value="'.(date('Y')+5).'">'.(date('Y')+5).'</option>
        <option value="'.(date('Y')+6).'">'.(date('Y')+6).'</option>
        <option value="'.(date('Y')+7).'">'.(date('Y')+7).'</option>
        <option value="'.(date('Y')+8).'">'.(date('Y')+8).'</option>
        <option value="'.(date('Y')+9).'">'.(date('Y')+9).'</option>
        <option value="'.(date('Y')+10).'">'.(date('Y')+10).'</option>
      </select></td></tr>
    <tr><td class="mw-creditcard-name">Card Security Code:</td><td class="mw-creditcard-input"><input type="text" name="cvv" size="5" maxlength="35" /></td></tr>
    <tr><td>
      <input type="hidden" name="process" value="CreditCard" />
      <input type="hidden" name="payment_method" value="processed" />
      <input type="hidden" name="token" value="'.$data['token'].'">
      <input type="hidden" name="currency_code" value="'.$data['currency'].'" />
      <input type="hidden" name="orderid" value="'.$data['order_id'].'" /></td>
    <td><input type="submit" value=" Donate " /></td></tr>
    </table>
    </form></div>
    </div>';
   
   //Implemented as a form with fieldsets
   //***Uncomment this section and comment out the previous table implementation if preferred***
   /*
    $form .= '<div class="mw-creditcard-form">
	<form name="payment" method="post" action="">
    		<legend class="mw-creditcard-amount">Donation Amount: '.$data['amount'].'</legend>
    			<input type="hidden" name="amount" value="'.$data['amount'].'">
		<fieldset class="mw-creditcard-donor">
    		<legend>Donor Information:</legend>
    			<p><label>E-mail Address:</label><input type="text" name="email" value="'.$data['email'].'" maxlength="150" size="30" /></p>
    			<p><label>First Name:</label><input type="text" name="fname" value="'.$data['fname'].'" maxlength="35" size="20" /></p>
    			<p><label>Middle Name:</label><input type="text" name="mname" value="'.$data['mname'].'" maxlength="35" size="20" /></p>
    			<p><label>Last Name:</label><input type="text" name="lname" value="'.$data['lname'].'" maxlength="35" size="20" /></p>
    			<p><label>Street:</label><input type="text" name="street" value="'.$data['street'].'" maxlength="100" size="30" /></p>
    			<p><label>City:</label><input type="text" name="city" value="'.$data['city'].'" maxlength="35" size="20" /></p>
    			<p><label>State:</label><select name="state" value="'.$data['state'].'" />'. statesMenu() .'</select></p>
    			<p><label>Postal Code:</label><input type="text" name="zip" value="'.$data['zip'].'" maxlength="18" size="15" /></p>
    			<p><label>Country/Region:</label><select name="country" value="'.$data['country'].'" />'.$countryMenu.'</select></p>
    </fieldset>
    	<fieldset class="mw-creditcard-label">
			<legend>Credit Card Information:</legend>
    			<p><label>Credit Card:</label>
					<select name="card">
    					<option value="visa">Visa</option><option selected="selected" value="mastercard">MasterCard</option>
    					<option value="american">American Express</option>
					</select></p>
    			<p><label>Card Number: </label><input type="text" name="card_num"  maxlength="100" /></p>
    			<p><label>Expiration Date:</label>
					<select name="mos">
    					<option value="01">01</option><option value="02">02</option><option value="03">03</option><option value="04">04</option>
    					<option value="05">05</option><option value="06">06</option><option value="07">07</option><option value="08">08</option>
    					<option value="09">09</option><option value="10">10</option><option value="11">11</option><option value="12" selected="selected">12</option>
    				</select>
					<select name="year">
    					<option value="'.(date('Y')).'">'.(date('Y')).'</option>
						<option value="'.(date('Y')+1).'">'.(date('Y')+1).'</option>
    					<option value="'.(date('Y')+2).'" selected="selected">'.(date('Y')+2).'</option>
						<option value="'.(date('Y')+3).'">'.(date('Y')+3).'</option>
    					<option value="'.(date('Y')+4).'">'.(date('Y')+4).'</option>
						<option value="'.(date('Y')+5).'">'.(date('Y')+5).'</option>
						<option value="'.(date('Y')+6).'">'.(date('Y')+6).'</option>
						<option value="'.(date('Y')+7).'">'.(date('Y')+7).'</option>
						<option value="'.(date('Y')+8).'">'.(date('Y')+8).'</option>
						<option value="'.(date('Y')+9).'">'.(date('Y')+9).'</option>
						<option value="'.(date('Y')+10).'">'.(date('Y')+10).'</option>
    				</select></p>
    			<p><label>Card Security Code:</label><input type="text" name="cvv" size="5" maxlength="35" /></p>
    			<input type="hidden" name="process" value="CreditCard" />
    			<input type="hidden" name="payment_method" value="processed" />
    			<input type="hidden" name="token" value="'.$data['token'].'">
    			<input type="hidden" name="currency_code" value="'.$data['currency'].'" />
    			<input type="hidden" name="orderid" value="'.$data['order_id'].'" />
   				<input type="submit" value=" Donate " />
   			</fieldset>
    </form>
  </div>
  </div>';
  */

    $wgOut->addHTML( $form ); 
		$wgOut->addHTML('<p class="mw-creditcard-submessage">This donation is being made in ' .$data['currency'] . '</p>'); 
	
  }
	
	
	/*
	 * Checks posted form data for errors and returns array of messages
	 */
	private function validateForm($data, &$error) {
    global $wgOut;
    
    //begin with no errors
	  $error_result = '0';
	  
	  //create the human-speak message for required fields
	  //does not include fields that are not required
	  $msg = array(  
		  'amount' => "donation amount",
		  'email' => "email address",
		  'fname' => "first name",
		  'lname' => "last name",
		  'street' => "street address",
		  'city' => "city",
		  'state' => "state",
		  'zip' => "zip code",
		  'card_num' => "credit card number",
		  'expiration' => "card's expiration date",
		  'cvv' => "the CVV from the back of your card",
		  );	  
	  
	  //find all empty fields and create message  
	  foreach($data as $key => $value) {
      if ($value == '') {
	     //ignore fields that are not required
	     if ($msg[$key]) {
	       $error[$key] = "**Please enter your " . $msg[$key] . "**<br />";
	       $error_result = '1';
	     }
      }
	   }
	   
	   //is email address valid?
	   $isEmail = eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $data['email']);
	   
	   //create error message (supercedes empty field message)
	   if (!$isEmail) {
	     $error['email'] = "**Please enter a valid email address**";
	     $error_result = '1';
	   }
	     
	   //validate that credit card number entered is correct for the brand
	   switch($data['card']) 
	   {
	     case 'american' :
	       //pattern for Amex
	       $pattern = "/^([34|37]{2})([0-9]{13})$/";
	       
	       //if the pattern doesn't match
	       if (!preg_match($pattern,$data['card_num'])) {
	         $error_result = '1';
	         $error['card'] = "**Please enter a correct card number for American Express.**";
	       }
	       break;
	     
	     case 'mastercard' :
	       //pattern for Mastercard
	       $pattern = "/^([51|52|53|54|55]{2})([0-9]{14})$/";
	       
	       //if pattern doesn't match
	       if (!preg_match($pattern,$data['card_num'])) {
	         $error_result = '1';
	         $error['card'] = "**Please enter a correct card number for Mastercard.**";
	       }
	       break;
	       
      case 'visa' :
        //pattern for Visa
        $pattern = "/^([4]{1})([0-9]{12,15})$/";
        
        //if pattern doesn't match
	       if (!preg_match($pattern,$data['card_num'])) {
	         $error_result = '1';
	         $error['card'] = "**Please enter a correct card number for Visa.**";
	       }
	       break;
	   }//end switch*/
 
	   return $error_result;
  }
  
  
  /*
  * Sends a name-value pair string to Payflow gateway
  *
  * parameters:
  * $data array of user input
  * $payflow_data array of necessary Payflow variables to include in string (ie Vendor, password)
  *
  */
  private function processTransaction($data, $payflow_data) {
    global $wgOut;
    
    /* Create name-value pair query string */
    $payflow_query = "TRXTYPE=$payflow_data[trxtype]&TENDER=$payflow_data[tender]&USER=$payflow_data[user]&VENDOR=$payflow_data[vendor]&PARTNER=$payflow_data[partner]&PWD=$payflow_data[password]&ACCT=$data[card_num]&EXPDATE=$data[expiration]&AMT=$data[amount]&FIRSTNAME=$data[fname]&LASTNAME=$data[lname]&STREET=$data[street]&ZIP=$data[zip]&INVNUM=$payflow_data[order_id]&CVV2=$data[cvv]&CURRENCY=$data[currency]&VERBOSITY=$payflow_data[verbosity]&CUSTIP=$payflow_data[user_ip]"; 
    
    // assign header data necessary for the curl_setopt() function
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $headers[] = "Content-Type: text/xml";
    $headers[] = "Content-Length : " . strlen ($payflow_query);
    $headers[] = "X-VPS-Timeout: 45";
    $headers[] = "X-VPS-Request-ID:" . $payflow_data['order_id'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $payflow_data['testingurl']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);   
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payflow_query);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, TRUE); 
    curl_setopt($ch, CURLOPT_POST, 1); 		

    // As suggested in the PayPal developer forum sample code, try more than once to get a response
    // in case there is a general network issue 
    $i=1;
    while ($i++ <= 3) {
      $result = curl_exec($ch);
      $headers = curl_getinfo($ch);
      if ($headers['http_code'] != 200) {
        sleep(5);
      } else if ($headers['http_code'] == 200) {
          break;
        }
    }
   
    if ($headers['http_code'] != 200) {
      $wgOut->addHTML('<h3>No response from credit card processor.  Please try again later!</h3><p>');
      curl_close($ch);
      exit;
    }
    
    curl_close($ch);
    
    // get result string
    $result = strstr($result, "RESULT");
   
    // parse string and display results to the user   
    $this->getResults($data, $result);
    
  }
  
  
  /*
   * "Reads" the name-value pair result string returned by Payflow and creates corresponding error messages
   *
   * @params:
   * $data array of user input
   * $result string of name-value pair results returned by Payflow
   *
   * Credit:code modified from payflowpro_example_EC.php posted (and supervised) on the PayPal developers message board
   */
  private function getResults($data, $result) {
    global $wgOut;
    
    //prepare NVP response for sorting and outputting 
    $responseArray = array();
    
    while(strlen($result)){
      // name
      $namepos= strpos($result,'=');
      $nameval = substr($result,0,$namepos);
      // value
      $valuepos = strpos($result,'&') ? strpos($result,'&'): strlen($result);
      $valueval = substr($result,$namepos+1,$valuepos-$namepos-1);
      // decoding the respose
      $responseArray[$nameval] = $valueval;
      $result = substr($result,$valuepos+1,strlen($result));
    }
    
    // errors fall into three categories, "try again please", "sorry it didn't work out", and "approved"
    // try again errors have not yet been developed 
    //   they will most likely return false and the form is displayed again with the error message (counted)
    // declines and approvals are displayed with data
    $resultCode = $responseArray['RESULT'];
    $responseMsg = "There was an problem processing the transaction.  Please try again or contact us.";
    $errorReturn = "0";
    
    
    //declines and approval
    if ($resultCode == '0') { 
      $responseMsg = "Your transaction has been approved.  Thank you for your donation!";
      $errorReturn = "1";
    } else if ($resultCode == "12" ) {
      $responseMsg = "Your transaction has been declined.  Please contact your credit card company for further information.";
      $errorReturn = "1";
    } else if ($resultCode == '13') { 
      $responseMsg = "Voice authorization is required.  Please contact us to continue the donation process.";
      $errorReturn = "1";   
    }
    
    //try again please
    
    //Development of this functionality is pending testing on live test site and further clarification from Paypal 
    /*
    if ($responseCode == '23' || $responseCode == '24') {
      $responseMsg = "Your credit card number or expiration date is incorrect.  Please try again.";
      $errorReturn = "1";
    } else if ()
    */
    
    $this->displayResults($data, $responseMsg);
    
  }// end display results
  
  
  /*
   * Display response message to user with submitted user-supplied data
   *
   * @params
   * $data array of posted data from form
   * $responseMsg message supplied by getResults function
   *
   */
  function displayResults($data, $responseMsg) {
    global $wgOut;
    
    require_once('includes/countryCodes.inc');
    
    // display response message
    $wgOut->addHTML('<h3 class="response_message">' . $responseMsg . "</h3>");
    
    //translate country code into text 
		$countries = countryCodes();
    
    // display user submitted info
    $submittedUserInfo = '<div class="submitted_response"><table>
      <tr>
      <td><h5>Transaction Details:</h5></td>
      </tr>
      <tr>
      <td>Amount: </td><td>'. $data['amount'] . '</td>
      </tr>
      <td>Email: </td><td>'. $data['email'] . '</td>
      </tr>
      <td>Name: </td><td>'. $data['fname'] . '</td>
      <td>'. $data['mname'] . '</td>
      <td>'. $data['lname'] . '</td>
      </tr>
      <td>Address: </td><td>'. $data['street'] . '</td>
      <td>'. $data['city'] . '</td>
      <td>'. $data['state'] . '</td>
      <td>'. $data['zip'] . '</td>
      <td>'. $countries[$data['country']] . '</td>
      </tr>
      </table>';
    
    $wgOut->addHTML($submittedUserInfo);
    
  }
  
  
} // end class
