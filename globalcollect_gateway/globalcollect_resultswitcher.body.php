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
		// TODO: Don't do that.
		$fake = $req->getBool( 'fake' );
		$fail = $req->getBool( 'fail' );

		if ( $fake ) {
			if ( $fail ) {
				$this->displayFailPage();
				return;
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
				$this->logger->error( "Resultswitcher: {$this->qs_oid} Is popped out, but still has no session data." );
			}
		}

		if ( $forbidden ){
			$this->logger->error( $this->qs_oid . " Resultswitcher: forbidden for reason: {$f_message}" );
			wfHttpError( 403, 'Forbidden', wfMessage( 'donate_interface-error-http-403' )->text() );
			return;
		}

		$this->setHeaders();
		$this->logger->info( "Resultswitcher: OK to process Order ID: " . $this->qs_oid );

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
						$this->logger->alert( "Resultswitcher: Parallel Universe Unlocked. Start time: $started" );
					}
					
					$_SESSION['pending'] = microtime( true ); //We couldn't have gotten this far if the server wasn't sticky.
					$result = $this->adapter->do_transaction( 'Confirm_CreditCard' );
					$session_info = array(
						//Just the stuff we use in displayResultsForDebug
						'data' => $result->getData(),
						'message' => $result->getMessage(),
						'errors' => $result->getErrors()
					);
					$_SESSION['order_status'][$oid] = $session_info;
					unset( $_SESSION['pending'] );
					$_SESSION['order_status'][$oid]['data']['count'] = 0;
				} else {
					$_SESSION['order_status'][$oid]['data']['count'] = $_SESSION['order_status'][$oid]['data']['count'] + 1;
					$this->logger->error( "Resultswitcher: Multiple attempts to process. " . $_SESSION['order_status'][$oid]['data']['count'] );
					$result = new PaymentTransactionResult();
					$result->setData( $_SESSION['order_status'][$oid]['data'] );
					$result->setMessage( $_SESSION['order_status'][$oid]['message'] );
					$result->setErrors( $_SESSION['order_status'][$oid]['errors'] );
				}
				$this->displayResultsForDebug( $result );
				//do the switching between the... stuff. 

				if ( $this->adapter->getFinalStatus() ){
					switch ( $this->adapter->getFinalStatus() ) {
						case FinalStatus::COMPLETE:
						case FinalStatus::PENDING:
						case FinalStatus::PENDING_POKE:
							$go = $this->adapter->getThankYouPage();
							break;
						case FinalStatus::FAILED:
							$this->displayFailPage();
							return;
					}

					if ( $go ) {
						$this->getOutput()->addHTML( "<br>Redirecting to page $go" );
						$this->getOutput()->redirect( $go );
					} else {
						$this->logger->error( "Resultswitcher: No redirect defined. Order ID: $oid" );
					}
				} else {
					$this->logger->error( "Resultswitcher: No FinalStatus. Order ID: $oid" );
				}
			} else {
				$this->logger->error( "Resultswitcher: Payment method is not cc. Order ID: $oid" );
			}
		} else {
			$this->logger->error("Resultswitcher: Token Check Failed. Order ID: $oid" );
		}
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
				$this->logger->error( "Resultswitcher: {$this->qs_oid} warning: iframed script cannot see session cookie." );
			}

			$_SESSION['order_status'][$this->qs_oid] = 'liberated';
			$this->logger->info( "Resultswitcher: Popping out of iframe for Order ID " . $this->qs_oid );

			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return true;
		}

		$this->logger->info( "Resultswitcher: good, it appears we are not in an iframe. Order ID {$this->qs_oid}" );
	}

	/**
	 * Overriding so the answer is correct in case we refactor handleRequest
	 * to use base class's handleResultRequest method.
	 */
	protected function isReturnFramed() {
		return true;
	}
}
