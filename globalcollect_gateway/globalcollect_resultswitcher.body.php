<?php

class GlobalCollectGatewayResult extends GatewayPage {
	/**
	 * Defines the action to take on a GlobalCollect transaction.
	 *
	 * Possible values include 'process', 'challenge',
	 * 'review', 'reject'.  These values can be set during
	 * data processing validation, for instance.
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

	protected $gatewayIdentifier = GlobalCollectAdapter::IDENTIFIER;

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
				$go = ResultPages::getThankYouPage( $this->adapter );
			}

			$this->getOutput()->addHTML( "<br>Redirecting to page $go" );
			$this->getOutput()->redirect( $go );
			return;
		}

		$forbidden = false;
		$this->qs_oid = $req->getText( 'order_id', '' );
		$this->qs_ref = $req->getText( 'REF', '' );
		if ( $this->qs_oid === '' && $this->qs_ref === '' ) {
			$forbidden = true;
			$f_message = 'No order ID in the Querystring.';
		} else {
			$result = $this->popout_if_iframe();
			if ( $result ) {
				return;
			}
		}

		$session_oid = $this->adapter->session_getData( 'Donor', 'order_id' );

		if ( is_null( $session_oid ) || ( ($this->qs_oid !== $session_oid) && strpos( $this->qs_ref, ( string ) $session_oid ) === false ) ) {
			$forbidden = true;
			$f_message = "Requested order id not present in the session. (session_oid = '$session_oid')";
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
			//this next block is for credit card coming back from GC. Only that. Nothing else, ever. 
			if ( $this->adapter->getData_Unstaged_Escaped( 'payment_method') === 'cc' ) {
				$sessionOrders = $req->getSessionData( 'order_status' );
				if ( !is_array( $sessionOrders )
					|| !isset( $sessionOrders[$this->qs_oid] )
					|| !is_array( $sessionOrders[$this->qs_oid] ) ) {

					$result = $this->adapter->do_transaction( 'Confirm_CreditCard' );
					$session_info = array(
						//Just the stuff we use in displayResultsForDebug
						'data' => $result->getData(),
						'message' => $result->getMessage(),
						'errors' => $result->getErrors()
					);
					$sessionOrders[$this->qs_oid] = $session_info;
					$sessionOrders[$this->qs_oid]['data']['count'] = 0;
				} else {
					$sessionOrders = $req->getSessionData( 'order_status' );
					$sessionOrders[$this->qs_oid]['data']['count'] = $sessionOrders[$this->qs_oid]['data']['count'] + 1;
					$this->logger->error( "Resultswitcher: Multiple attempts to process. " . $sessionOrders[$this->qs_oid]['data']['count'] );
					$result = new PaymentTransactionResult();
					$result->setData( $sessionOrders[$this->qs_oid]['data'] );
					$result->setMessage( $sessionOrders[$this->qs_oid]['message'] );
					$result->setErrors( $sessionOrders[$this->qs_oid]['errors'] );
				}
				$req->setSessionData( 'order_status', $sessionOrders );
				$this->displayResultsForDebug( $result );
				//do the switching between the... stuff. 

				$status = $this->adapter->getFinalStatus();
				if ( $status ) {
					switch ( $status ) {
						case FinalStatus::COMPLETE:
						case FinalStatus::PENDING:
						case FinalStatus::PENDING_POKE:
							$this->logger->info( "Displaying thank you page for final status $status" );
							$go = ResultPages::getThankYouPage( $this->adapter );
							break;
						case FinalStatus::FAILED:
							$this->logger->info( 'Displaying fail page for final status failed.' );
							$this->displayFailPage();
							return;
					}

					if ( $go ) {
						$this->getOutput()->addHTML( "<br>Redirecting to page $go" );
						$this->getOutput()->redirect( $go );
						return;
					} else {
						$this->logger->error( "Resultswitcher: No redirect defined. Order ID: {$this->qs_oid}" );
					}
				} else {
					$this->logger->error( "Resultswitcher: No FinalStatus. Order ID: {$this->qs_oid}" );
				}
			} else {
				$this->logger->error( "Resultswitcher: Payment method is not cc. Order ID: {$this->qs_oid}" );
			}
		} else {
			$this->logger->error("Resultswitcher: Token Check Failed. Order ID: {$this->qs_oid}" );
		}
		$this->displayFailPage();
	}

	function popout_if_iframe() {
		global $wgServer;

		$request = $this->getRequest();
		$qs_liberated = $request->getText( 'liberated', '' );
		if ( $this->adapter->session_getData( 'order_status', $this->qs_oid ) === 'liberated'
			|| $qs_liberated !== '' ) {
			return;
		}

		// @todo Whitelist! We only want to do this for servers we are configured to like!
		//I didn't do this already, because this may turn out to be backwards anyway. It might be good to do the work in the iframe, 
		//and then pop out. Maybe. We're probably going to have to test it a couple different ways, for user experience. 
		//However, we're _definitely_ going to need to pop out _before_ we redirect to the thank you or fail pages. 
		$referrer = $request->getHeader( 'referer' );
		if ( ( strpos( $referrer, $wgServer ) === false ) ) {

			$sessionOrders = $request->getSessionData( 'order_status' );
			$sessionOrders[$this->qs_oid] = 'liberated';
			$request->setSessionData( 'order_status', $sessionOrders );
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
