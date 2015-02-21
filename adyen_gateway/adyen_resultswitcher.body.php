<?php

class AdyenGatewayResult extends GatewayPage {

	/**
	 * Defines the action to take on a Adyen transaction.
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

	public function __construct() {
		$this->adapter = new AdyenAdapter();
		parent::__construct();
	}

	/**
	 * Show the special page
	 */
	protected function handleRequest() {

		//no longer letting people in without these things. If this is 
		//preventing you from doing something, you almost certainly want to be 
		//somewhere else. 
		$forbidden = false;
		if ( !$this->adapter->session_hasDonorData() ) {
			$forbidden = true;
			$f_message = 'No active donation in the session';
		}
		
		if ( $forbidden ){
			wfHttpError( 403, 'Forbidden', wfMsg( 'donate_interface-error-http-403' ) );
		}
		$oid = $this->adapter->getData_Unstaged_Escaped( 'order_id' );

		$referrer = $this->getRequest()->getHeader( 'referer' );
		$liberated = false;
		if ( $this->adapter->session_getData( 'order_status', $oid ) === 'liberated' ) {
			$liberated = true;
		}

		global $wgServer;
		if ( ( strpos( $referrer, $wgServer ) === false ) && !$liberated ) {
			$_SESSION[ 'order_status' ][ $oid ] = 'liberated';
			$this->adapter->log("Resultswitcher: Popping out of iframe for Order ID " . $oid);
			//TODO: Move the $forbidden check back to the beginning of this if block, once we know this doesn't happen a lot.
			//TODO: If we get a lot of these messages, we need to redirect to something more friendly than FORBIDDEN, RAR RAR RAR.
			if ( $forbidden ) {
				$this->adapter->log("Resultswitcher: $oid SHOULD BE FORBIDDEN. Reason: $f_message", LOG_ERR);
			}
			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return;
		}

		$this->setHeaders();

		if ( $forbidden ){
			$this->adapter->log( "Resultswitcher: Request forbidden. " . $f_message . " Adapter Order ID: $oid", LOG_CRIT );
			return;
		} else {
			$this->adapter->log( "Resultswitcher: OK to process Order ID: " . $oid );
		}

		if ( $this->adapter->checkTokens() ) {

			if ( $this->adapter->isResponse() ) {
				$this->getOutput()->allowClickjacking();
				$this->getOutput()->addModules( 'iframe.liberator' );
				if ( NULL === $this->adapter->processResponse() ) {
					switch ( $this->adapter->getFinalStatus() ) {
					case 'complete':
					case 'pending':
						$this->getOutput()->redirect( $this->adapter->getThankYouPage() );
						return;
					}
				}
				$this->getOutput()->redirect( $this->adapter->getFailPage() );
			}
		} else {
			$this->adapter->log( "Resultswitcher: Token Check Failed. Order ID: $oid", LOG_ERR );
		}
	}

}
