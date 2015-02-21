<?php

class GlobalCollectGatewayResult extends GatewayPage {
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

	protected $qs_oid = null;

	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new GlobalCollectAdapter();
		parent::__construct(); //the next layer up will know who we are. 
	}

	/**
	 * Show the special page
	 */
	protected function handleRequest() {
		$req = $this->getRequest();
		$fake = $req->getBool( 'fake' );
		$fail = $req->getBool( 'fail' );

		if ( $fake ) {
			if ( $fail ) {
				$go = $this->getDeclinedResultPage();
			} else {
				$go = $this->adapter->getThankYouPage();
			}

			$this->getOutput()->addHTML( "<br>Redirecting to page $go" );
			$this->getOutput()->redirect( $go );
			return;
		}

		$forbidden = false;
		if ( !isset( $_GET['order_id'] ) && !isset( $_GET['REF'] ) ) {
			$forbidden = true;
			$f_message = 'No order ID in the Querystring.';
		} else {
			isset( $_GET['order_id'] ) ? $this->qs_oid = $_GET['order_id'] : $this->qs_oid = null;
			$result = $this->popout_if_iframe();
			if ( $result ) {
				return;
			}
		}

		$session_oid = $this->adapter->session_getData( 'Donor', 'order_id' );

		if ( is_null( $session_oid ) || ( ($this->qs_oid !== $session_oid) && strpos( $_GET['REF'], ( string ) $session_oid ) === false ) ) {
			$forbidden = true;
			$f_message = "Requested order id not present in the session. (session_oid = '$session_oid')";

			if ( !$_SESSION ) {
				$this->adapter->log( "Resultswitcher: {$this->qs_oid} Is popped out, but still has no session data.", LOG_ERR );
			}
		}

		if ( $forbidden ){
			$this->adapter->log( $this->qs_oid . " Resultswitcher: forbidden for reason: {$f_message}", LOG_ERR );
			wfHttpError( 403, 'Forbidden', wfMessage( 'donate_interface-error-http-403' )->text() );
			return;
		}

		$this->setHeaders();
		$this->adapter->log( "Resultswitcher: OK to process Order ID: " . $this->qs_oid );

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			// Display form for the first time
			$oid = $this->getRequest()->getText( 'order_id' );

			//this next block is for credit card coming back from GC. Only that. Nothing else, ever. 
			if ( $this->adapter->getData_Unstaged_Escaped( 'payment_method') === 'cc' ) {
				if ( !is_array( $this->adapter->session_getData( 'order_status', $oid ) ) ) {

					//@TODO: If you never, ever, ever see this, rip it out. 
					//In all other cases, write kind of a lot of code. 
					if ( array_key_exists( 'pending', $_SESSION ) ){
						$started = $_SESSION['pending'];
						//not sure what to do with this yet, but I sure want to know if it's happening. 
						$this->adapter->log( "Resultswitcher: Parallel Universe Unlocked. Start time: $started", LOG_ALERT );
					}
					
					$_SESSION['pending'] = microtime( true ); //We couldn't have gotten this far if the server wasn't sticky. 
					$_SESSION['order_status'][$oid] = $this->adapter->do_transaction( 'Confirm_CreditCard' );
					unset( $_SESSION['pending'] );
					$_SESSION['order_status'][$oid]['data']['count'] = 0;
				} else {
					$_SESSION['order_status'][$oid]['data']['count'] = $_SESSION['order_status'][$oid]['data']['count'] + 1;
					$this->adapter->log( "Resultswitcher: Multiple attempts to process. " . $_SESSION['order_status'][$oid]['data']['count'], LOG_ERR );
				}
				$result = $_SESSION['order_status'][$oid];
				$this->displayResultsForDebug( $result );
				//do the switching between the... stuff. 

				if ( $this->adapter->getFinalStatus() ){
					switch ( $this->adapter->getFinalStatus() ) {
						case 'complete':
						case 'pending':
						case 'pending-poke':
							$go = $this->adapter->getThankYouPage();
							break;
						case 'failed':
							$go = $this->getDeclinedResultPage();
							break;
					}

					if ( $go ) {
						$this->getOutput()->addHTML( "<br>Redirecting to page $go" );
						$this->getOutput()->redirect( $go );
					} else {
						$this->adapter->log("Resultswitcher: No redirect defined. Order ID: $oid", LOG_ERR);
					}
				} else {
					$this->adapter->log("Resultswitcher: No FinalStatus. Order ID: $oid", LOG_ERR);
				}
			} else {
				$this->adapter->log("Resultswitcher: Payment method is not cc. Order ID: $oid", LOG_ERR);
			}
		} else {
			$this->adapter->log("Resultswitcher: Token Check Failed. Order ID: $oid", LOG_ERR);
		}
	}
	
	/**
	 * Get the URL to redirect to when the transaction has been declined. This will be the form the
	 * user came from with all the data and an error message.
	 */
	function getDeclinedResultPage() {
		$displayData = $this->adapter->getData_Unstaged_Escaped();
		$failpage = $this->adapter->getFailPage();

		if ( !$failpage ) {
			throw new MWException( __FUNCTION__ . ': No declined result page defined. Please define a FailPage global for ' . $this->adapter->getGatewayIdentifier() );
		}
		return $failpage;
	}

	function popout_if_iframe() {
		global $wgServer;

		if ( ( $this->adapter->session_getData( 'order_status', $this->qs_oid ) === 'liberated' ) || isset( $_GET['liberated'] ) ) {
			return;
		}

		// @todo Whitelist! We only want to do this for servers we are configured to like!
		//I didn't do this already, because this may turn out to be backwards anyway. It might be good to do the work in the iframe, 
		//and then pop out. Maybe. We're probably going to have to test it a couple different ways, for user experience. 
		//However, we're _definitely_ going to need to pop out _before_ we redirect to the thank you or fail pages. 
		$referrer = $this->getRequest()->getHeader( 'referer' );
		if ( ( strpos( $referrer, $wgServer ) === false ) ) {
			if ( !$_SESSION ) {
				$this->adapter->log( "Resultswitcher: {$this->qs_oid} warning: iframed script cannot see session cookie.", LOG_ERR );
			}

			$_SESSION['order_status'][$this->qs_oid] = 'liberated';
			$this->adapter->log("Resultswitcher: Popping out of iframe for Order ID " . $this->qs_oid);

			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return true;
		}

		$this->adapter->log( "Resultswitcher: good, it appears we are not in an iframe. Order ID {$this->qs_oid}" );
	}
}
