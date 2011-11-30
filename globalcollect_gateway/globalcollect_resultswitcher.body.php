<?php

class GlobalCollectGatewayResult extends GatewayForm {

	/**
	 * Defines the action to take on a GlobalCollect transaction.
	 *
	 * Possible values include 'process', 'challenge',
	 * 'review', 'reject'.  These values can be set during
	 * data processing validation, for instance.
	 *
	 * Hooks are exposed to handle the different actions.
	 *
	 * Defaults to 'process'.
	 * @var string
	 */
	public $action = 'process';

	/**
	 * An array of form errors
	 * @var array
	 */
	public $errors = array( );

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new GlobalCollectAdapter();
		parent::__construct(); //the next layer up will know who we are. 
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgRequest, $wgOut, $wgExtensionAssetsPath;

		//no longer letting people in without these things. If this is 
		//preventing you from doing something, you almost certainly want to be 
		//somewhere else. 
		$forbidden = false;
		if ( !isset($_GET['order_id']) || !$this->adapter->hasDonorDataInSession( 'order_id', $_GET['order_id'] ) ){
			//TODO: i18n, apparently. 
			wfHttpError( 403, 'Forbidden', 'You do not have permission to access this page.' );
			$forbidden = true;
		}

		$referrer = $wgRequest->getHeader( 'referer' );

		global $wgServer;
		//TODO: Whitelist! We only want to do this for servers we are configured to like!
		//I didn't do this already, because this may turn out to be backwards anyway. It might be good to do the work in the iframe, 
		//and then pop out. Maybe. We're probably going to have to test it a couple different ways, for user experience. 
		//However, we're _definitely_ going to need to pop out _before_ we redirect to the thank you or fail pages. 
		if ( strpos( $referrer, $wgServer ) === false ) {			
			$wgOut->allowClickjacking();
			$wgOut->addModules( 'iframe.liberator' );
			return;
		}
		
		$wgOut->addExtensionStyle(
			$wgExtensionAssetsPath . '/DonationInterface/gateway_forms/css/gateway.css?284' .
			$this->adapter->getGlobal( 'CSSVersion' ) );

		$this->setHeaders();
		
		if ( $forbidden ){
			$qs_oid = 'undefined';
			$message = '';
			if ( !isset($_GET['order_id']) ){
				$message = 'No order ID in the Querystring.';
			} else {
				$qs_oid = $_GET['order_id'];
			}
			
			if ( !$this->adapter->hasDonorDataInSession( 'order_id', $_GET['order_id'] ) ){
				$message = 'Requested order id not present in the session';
			}
			
			$this->adapter->log("Resultswitcher: Request forbidden. " . $message . " Quersytring Oirder ID: $qs_oid");
			return;
		}


		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			// Display form for the first time
			$oid = $wgRequest->getText( 'order_id' );
			
			//this next block is for credit card coming back from GC. Only that. Nothing else, ever. 
			if ( $this->adapter->getData_Raw( 'payment_method') === 'cc' ) {
				if ( !array_key_exists( 'order_status', $_SESSION ) || !array_key_exists( $oid, $_SESSION['order_status'] ) ) {
					$_SESSION['order_status'][$oid] = $this->adapter->do_transaction( 'Confirm_CreditCard' );
					$_SESSION['order_status'][$oid]['data']['count'] = 0;
				} else {
					$_SESSION['order_status'][$oid]['data']['count'] = $_SESSION['order_status'][$oid]['data']['count'] + 1;
				}
				$result = $_SESSION['order_status'][$oid];
				$this->displayResultsForDebug( $result );
				//do the switching between the... stuff. 

				if ( $this->adapter->getTransactionWMFStatus() ){
					switch ( $this->adapter->getTransactionWMFStatus() ) {
						case 'complete':
						case 'pending':
						case 'pending-poke':
							$go = $this->adapter->getThankYouPage();
							break;
						case 'failed':
							$go = $this->getDeclinedResultPage();
							break;
					}

					if ($go) {
						$wgOut->addHTML( "<br>Redirecting to page $go" );
						$wgOut->redirect( $go );
					} else {
						$this->adapter->log("Resultswitcher: No redirect defined. Order ID: $oid");
					}
				} else {
					$this->adapter->log("Resultswitcher: No TransactionWMFStatus. Order ID: $oid");
				}
			} else {
				$this->adapter->log("Resultswitcher: Payment method is not cc. Order ID: $oid");
			}
		} else {
			$this->adapter->log("Resultswitcher: Token Check Failed. Order ID: $oid");
		}
	}
	
	/**
	 * Get the URL to redirect to when the transaction has been declined. This will be the form the
	 * user came from with all the data and an error message.
	 */
	function getDeclinedResultPage() {
		global $wgOut;
		
		$displayData = $this->adapter->getData_Raw();
		$failpage = $this->adapter->getFailPage();

		if ( $failpage ) {
			return $failpage;
		} else {
			// Get the page we're going to send them back to.
			$referrer = $displayData['referrer'];
			$returnto = htmlspecialchars_decode( $referrer ); // escape for security
			
			// Set the response as failure so that an error message will be displayed when the form reloads.
			$this->adapter->addData( array( 'response' => 'failure' ) );
			
			// Store their data in the session.
			$this->adapter->addDonorDataToSession();
			
			// Return the referrer URL
			return $returnto;
		}
	}
	
}

// end class
