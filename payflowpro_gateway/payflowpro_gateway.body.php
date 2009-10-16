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
		        
		        $wgOut->addHeadItem('validatescript', '<script type="text/javascript" language="javascript" src="/extensions/DonationInterface/payflowpro_gateway/validate_input.js"></script>');
		
		        //create token if one doesn't already exist
		        $token = $wgUser->editToken('mrxc877668DwQQ');
		       
		        // Declare form post variables 
		        $data = array(
		                'amount'         => '',
		                'email'          => '',
		                'fname'          => '',
		                'mname'          => '',
		                'lname'          => '',
		                'street'         => '',
		                'city'           => '',
		                'state'          => '',
		                'zip'            => '',
		                'country'        => '',
		                'card_num'       => '',
		                'expiration'     => '',
		                'cvv'            => '',
		                'currency'       => '',
		                'payment_method' => '',
		                'order_id'       => '',
		                'numAttempt'     => '',
		                'token'          => $token,
		        );
		  
		        $error[] = '';
		
		        //find out if amount was a radio button or textbox, set amount
		        if (isset($_REQUEST['amount'])) {
		                $amount = $wgRequest->getText('amount');
		        } else if (isset($_REQUEST['amount2'])) { 
		                $amount = number_format($wgRequest->getText('amount2'), 2, '.', ''); 
		        } else { 
		                $wgOut->addHTML(wfMsg( 'pfp-accessible' )); 
		                return;
		        }
           
            //track the number of attempts the user has made
            $numAttempt = ($wgRequest->getText('numAttempt') == '') ? '0' : $wgRequest->getText('numAttempt');
		        // Populate from data  
		        $data = array(  
		                'amount'         => $amount,
		                'email'          => $wgRequest->getText('email'),
		                'fname'          => $wgRequest->getText('fname'),
		                'mname'          => $wgRequest->getText('mname'),
		                'lname'          => $wgRequest->getText('lname'),
		                'street'         => $wgRequest->getText('street'),
		                'city'           => $wgRequest->getText('city'),
		                'state'          => $wgRequest->getText('state'),
		                'zip'            => $wgRequest->getText('zip'),
		                'country'        => $wgRequest->getText('country'),
		                'card'           => $wgRequest->getText('card'),
		                'card_num'       => str_replace(' ','',$wgRequest->getText('card_num')),
		                'expiration'     => $wgRequest->getText('mos').substr($wgRequest->getText('year'), 2, 2),
		                'cvv'            => $wgRequest->getText('cvv'),
		                'currency'       => $wgRequest->getText('currency_code'),
		                'payment_method' => $wgRequest->getText('payment_method'),
		                'order-id'       => NULL, //will be set with $payflow_data
		                'numAttempt'     => $numAttempt,
		                'test_string'    => $wgRequest->getText('process'), //for showing payflow string during testing
		      );
		
		      // Get array of default account values necessary for Payflow 
		      require_once('includes/payflowUser.inc');
		
		      $payflow_data = payflowUser();
		      
		      //assign this order ID to the $data array as well
		      $data['order_id'] = $payflow_data['order_id'];
		
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
                                  //increase the count of attempts
                                  ++$data['numAttempt'];
                                  $this->processTransaction($data, $payflow_data);
                          }
                  } else {
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
   * The message at the top of the form can be edited in the payflow_gateway.i18.php file 
   */
  private function displayForm($data, &$error) {
          require_once('includes/stateAbbreviations.inc');
		      require_once('includes/countryCodes.inc');
		
		      global $wgOut;	
		   
		      $form = XML::openElement('div', array('id' => 'mw-creditcard')) .
		              XML::openElement('div', array('id' => 'mw-creditcard-intro')) .
		              XML::tags('p', array('class' => 'mw-creditcard-intro-msg'), wfMsg( 'pfp-form-message' )) .
		              XML::tags('p', array('class' => 'mw-creditcard-intro-msg'), wfMsg( 'pfp-form-message-2' )) .
                  XML::closeElement('div');
		      
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
		      $countries = countryCodes();
		      
		      foreach($countries as $value => $fullName) {
		              $countryMenu .= XML::option($fullName, $value);
		      }
		      
          //Form 
          $form .= XML::openElement('div', array('id' => 'mw-creditcard-form')) . 
                  XML::openElement('form', array('name' => "payment", 'method' => "post", 'action' => "", 'onsubmit' => 'return validate_form(this)')) .
                  XML::element('legend', array('class' => 'mw-creditcard-amount'), wfMsg( 'pfp-amount-legend' ) .$data['amount']) .
                  XML::hidden('amount', $data['amount']);
          
          $donorInput = array(
                  XML::inputLabel(wfMsg( 'pfp-donor-email' ), "email", "email", "30", $data['email'], array('maxlength' => "150")),
                  XML::inputLabel(wfMsg( 'pfp-donor-fname' ), "fname", "fname", "20", $data['fname'], array('maxlength' => "35", 'class' => 'required')),
                  XML::inputLabel(wfMsg( 'pfp-donor-mname' ), "mname", "mname", "20", $data['mname'], array('maxlength' => "35")),
                  XML::inputLabel(wfMsg( 'pfp-donor-lname' ), "lname", "lname", "20", $data['lname'], array('maxlength' => "35")),
                  XML::inputLabel(wfMsg( 'pfp-donor-street' ), "street", "street", "30", $data['street'], array('maxlength' => "100")),
                  XML::inputLabel(wfMsg( 'pfp-donor-city' ), "city", "city", "20", $data['city'], array('maxlength' => "35")),
                  XML::label(wfMsg( 'pfp-donor-state' ), "state") .
                  XML::openElement('select', array('name' => "state", 'id' => "state", 'value' => $data['state'])) .
                  statesMenuXML() . 
                  XML::closeElement('select'),
                  XML::inputLabel(wfMsg( 'pfp-donor-postal' ), "zip", "zip", "15", $data['zip'], array('maxlength' => "18")),
                  XML::label(wfMsg( 'pfp-donor-country' ), "country") .
                  XML::openElement('select', array('name' => "country", 'id' => "country", 'value' => $data['country'])) .
                  $countryMenu . 
                  XML::closeElement('select')
          );
            
          $donorField = "";
              
          foreach($donorInput as $value) {
                  $donorField .= '<p>' . $value . '</p>';
          }  
          
          $form .= XML::fieldset(wfMsg( 'pfp-donor-legend' ), $donorField,  array('class' => "mw-creditcard-donor"));
		        
		      $cardOptions = array(
		              'visa'       => "Visa",
		              'mastercard' => "Mastercard",
		              'american'   => "American Express",
		      );
		        
		      foreach($cardOptions as $value => $fullName) {
		              $cardOptionsMenu .= XML::option($fullName, $value);
		      }
		        
		      $cardInput = 
		                XML::label(wfMsg( 'pfp-donor-card' ), "card") .
                                XML::openElement('select', array('name' => "card", 'id' => "card")) .
                                $cardOptionsMenu .
                                XML::closeElement('select');
            
          $expMos = '';
              
          for($i=1; $i<13; $i++) {
                  $expMos .= XML::option(str_pad($i, 2, '0', STR_PAD_LEFT), str_pad($i, 2, '0', STR_PAD_LEFT));
          }
		          
		      $expMosMenu = 
		                XML::label(wfMsg( 'pfp-donor-expiration' ), "expiration") .
                                XML::openElement('select', array('name' => "mos", 'id' => "mos")) .
                                $expMos .
                                XML::closeElement('select');
            
          $expYr = '';
              
          for($i=0; $i<11; $i++) {
                  $expYr .= XML::option(date('Y')+$i, date('Y')+$i);
          }
            
          $expYrMenu = 
                  XML::openElement('select', array('name' => "year", 'id' => "year")) .
                  $expYr .
                  XML::closeElement('select');
             
          $cardInput = array(
                  XML::label(wfMsg( 'pfp-donor-card' ), "card") .
                  XML::openElement('select', array('name' => "card", 'id' => "card")) .
                  $cardOptionsMenu .
                  XML::closeElement('select'),
                  XML::inputLabel(wfMsg( 'pfp-donor-card-num' ), "card_num", "card_num", "30", '', array('maxlength' => "100")),
                  $expMosMenu . $expYrMenu,
                  XML::inputLabel(wfMsg( 'pfp-donor-security' ), "cvv", "cvv", "5", '', array('maxlength' => "10")),
          );
            
          foreach($cardInput as $value) {
                  $cardField .= '<p>' . $value . '</p>';
          } 
            
          $form .= XML::fieldset(wfMsg( 'pfp-card-legend' ), $cardField,  array('class' => "mw-creditcard-card")) .
                  XML::hidden('process', 'CreditCard') .
                  XML::hidden('payment_method', 'processed') .
                  XML::hidden('token', $data['token']) .
                  XML::hidden('currency_code', $data['currency']) . 
                  XML::hidden('orderid', $data['order_id']) .
                  XML::hidden('numAttempt', $data['numAttempt']) .
                  XML::submitButton("Donate") . 
                  XML::closeElement('form') .
                  XML::closeElement('div');
               
           
          $form .= XML::closeElement('div') . 
		              XML::Element('p', array('class' => "mw-creditcard-submessage"), wfMsg( 'pfp-donor-currency-msg', $data['currency'] ) ); 
          $wgOut->addHTML( $form );
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
		              'amount'     => "donation amount",
		              'email'      => "email address",
		              'fname'      => "first name",
		              'lname'      => "last name",
		              'street'     => "street address",
		              'city'       => "city",
		              'state'      => "state",
		              'zip'        => "zip code",
		              'card_num'   => "credit card number",
		              'expiration' => "card's expiration date",
		              'cvv'        => "the CVV from the back of your card",
		        );	  
	        
	        //find all empty fields and create message  
	        foreach($data as $key => $value) {
                  if ($value == '') {
                          //ignore fields that are not required
	                       if ($msg[$key]) {
	                               $error[$key] = "**" . wfMsg( 'pfp-error-msg', $msg[$key] ) . "**<br />";
	                               $error_result = '1';
	                       }
                  }
	         }
	         
	         //is email address valid?
	         $isEmail = eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $data['email']);
	         
	         //create error message (supercedes empty field message)
	         if (!$isEmail) {
	                   $error['email'] = wfMsg ( 'pfp-error-msg-email' );
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
	                                 $error['card'] = wfMsg( 'pfp-error-msg-amex' );
	                         }
	                         
	                         break;
	                 
	                 case 'mastercard' :
	                         //pattern for Mastercard
	                         $pattern = "/^([51|52|53|54|55]{2})([0-9]{14})$/";
	                   
	                         //if pattern doesn't match
	                         if (!preg_match($pattern,$data['card_num'])) {
	                           $error_result = '1';
	                           $error['card'] = wfMsg( 'pfp-error-msg-mc' );
	                         }
	                         
	                         break;
	                   
	                 case 'visa' :
                          //pattern for Visa
                          $pattern = "/^([4]{1})([0-9]{12,15})$/";
                    
                          //if pattern doesn't match
	                         if (!preg_match($pattern,$data['card_num'])) {
	                                 $error_result = '1';
	                                 $error['card'] = wfMsg( 'pfp-error-msg-visa' );
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
          
          //NOTE: for testing
          //var_dump($payflow_query);
          
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
          
          //NOTE for testing
          //var_dump($result);
          
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
          
          //errors fall into three categories, "try again please", "sorry it didn't work out", and "approved"
          //get the result code for response array
          $resultCode = $responseArray['RESULT'];
          
          //initialize response message
          $tryAgainResponse = '';
          $responseMsg = '';
          
          //interpret result code, return
          //approved (1), denied (2), try again (3), general error (4)
          $errorCode = $this->getResponseMsg($resultCode, $responseMsg);
         
          //if approved, display results and send transaction to the queue
          if ($errorCode == '1') {      
                  $this->displayApprovedResults($data, $responseArray, $responseMsg);
          //give user a second chance to enter incorrect data
          } else if (($errorCode == '3') && ($data['numAttempt'] < '2')) {
                  //pass responseMsg as an array key as required by displayForm
                  $tryAgainResponse[$responseMsg] = $responseMsg;
                  $this->displayForm($data, $tryAgainResponse);
          // if declined or if user has already made two attempts, decline
          } else if (($errorCode == '2') || ($data['numAttempt'] >= '2')) { 
                  $this->displayDeclinedResults($responseMsg);
          }
                  
  }// end display results
  
  /**
   * Interpret response code, return 
   * 1 if approved
   * 2 if declined
   * 3 if invalid data was submitted by user
   * 4 all other errors
   */
   function getResponseMsg($resultCode, &$responseMsg) {
          $responseMsg = wfMsg( 'pfp-response-default' );
          $errorCode = "0";
          
          switch ($resultCode) {
                  case '0':
                          $responseMsg = wfMsg( 'pfp-response-0' );
                          $errorCode = "1";
                          break;
                  case '126':
                          $responseMsg = wfMsg( 'pfp-response-126' );
                          $errorCode = "1";
                          break;
                  case '12':
                          $responseMsg = wfMsg( 'pfp-response-12' );
                          $errorCode = "2";
                          break;
                  case '13':
                          $responseMsg = wfMsg( 'pfp-response-13' );
                          $errorCode = "2";
                          break;
                  case '114':
                          $responseMsg = wfMsg( 'pfp-response-114' );
                          $errorCode = "2";
                          break;
                  case '4':
                          $responseMsg = wfMsg( 'pfp-response-4' );
                          $errorCode = "3";
                          break;
                  case '23':
                          $responseMsg = wfMsg( 'pfp-response-23' );
                          $errorCode = "3";
                          break;
                  case '24':
                          $responseMsg = wfMsg( 'pfp-response-24' );
                          $errorCode = "3";
                          break;
                  case '112':
                          $responseMsg = wfMsg( 'pfp-response-112' );
                          $errorCode = "3";
                          break;
                  case '125':
                          $responseMsg = wfMsg( 'pfp-response-125' );
                          $errorCode = "3";
                          break;
                  default:
                          $responseMsg = wfMsg( 'pfp-response-default' );
                          $errorCode = "4";
                  
          }
         
          return $errorCode;
} 
   
  /*
   * Display response message to user with submitted user-supplied data
   *
   * @params
   * $data array of posted data from form
   * $responseMsg message supplied by getResults function
   *
   */
  function displayApprovedResults($data, $responseArray, $responseMsg) {
          global $wgOut;
          $transaction = '';
          
          require_once('includes/countryCodes.inc');
          
          // display response message
          $wgOut->addHTML('<h3 class="response_message">' . $responseMsg . "</h3>");
     
          
          //translate country code into text 
		      $countries = countryCodes();
          
          $rows = array(
                  'title' => array(wfMsg( 'pfp-post-transaction' )), 
                  'amount' => array(wfMsg( 'pfp-donor-amount' ), $data['amount']),
                  'email' => array(wfMsg( 'pfp-donor-email' ), $data['email']),
                  'name' => array(wfMsg( 'pfp-donor-name' ), $data['fname'], $data['mname'], $data['lname']),
                  'address' => array(wfMsg( 'pfp-donor-address' ), $data['street'], $data['city'], $data['state'], $data['zip'],$countries[$data['country']]),
          );
          
          //if we want to show the response
          $wgOut->addHTML(XML::buildTable($rows, array('class' => 'submitted-response')));
          
          //push to ActiveMQ server 
          // include response message
          $transaction['response'] = $responseMsg;
          // include date
          $transaction['date'] = time();
          // send both the country as text and the three digit ISO code
          $transaction['country_name'] = $countries[$data['country']];
          $transaction['country_code'] = $data['country'];
          //put all data into one array
          $transaction += array_merge($data, $responseArray);  
          
          // hook to call stomp functions    
          wfRunHooks('gwStomp', array(&$transaction));
          
          
  }       
  
  /*
   * Display response message to user with submitted user-supplied data
   *
   * @params
   * $data array of posted data from form
   * $responseMsg message supplied by getResults function
   *
   */
  function displayDeclinedResults($responseMsg) {
          global $wgOut;
          
          //general decline message
          $declinedDefault = wfMsg( 'php-response-declined' );
                  
          // display response message
          $wgOut->addHTML('<h3 class="response_message">' . $declinedDefault . $responseMsg . "</h3>");
               
          
  }       

  
  
} // end class
