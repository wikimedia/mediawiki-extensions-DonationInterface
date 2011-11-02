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


		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			// Display form for the first time
			$oid = $wgRequest->getText( 'order_id' );
			$adapter_oid = $this->adapter->getData();
			$adapter_oid = $adapter_oid['order_id'];
			if ( $oid && !empty( $oid ) && $oid === $adapter_oid ) {
				if ( !array_key_exists( 'order_status', $_SESSION ) || !array_key_exists( $oid, $_SESSION['order_status'] ) ) {
					$_SESSION['order_status'][$oid] = $this->adapter->do_transaction( 'GET_ORDERSTATUS' );
					$_SESSION['order_status'][$oid]['data']['count'] = 0;
				} else {
					$_SESSION['order_status'][$oid]['data']['count'] = $_SESSION['order_status'][$oid]['data']['count'] + 1;
				}
				$result = $_SESSION['order_status'][$oid];
				$this->displayResultsForDebug( $result );
				//do the switching between the... stuff. 

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

				$wgOut->addHTML( "<br>Redirecting to page $go" );
				$wgOut->redirect( $go );
			}
		} else {
			if ( !$this->adapter->isCache() ) {
				// if we're not caching, there's a token mismatch
				$this->errors['general']['token-mismatch'] = wfMsg( 'donate_interface-token-mismatch' );
			}
		}
	}
	
	/**
	 * Get the URL to redirect to when the transaction has been declined. This will be the form the
	 * user came from with all the data and an error message.
	 */
	function getDeclinedResultPage() {
		global $wgOut;
		$failpage = $this->adapter->getGlobal( 'FailPage' );

		if ( $failpage ) {
			$wgOut->redirect( $failpage . "/" . $data['language'] );
		} else {
			// general decline message
			$declinedDefault = wfMsg( 'php-response-declined' );
			
			$data = $this->adapter->getData();
			$referrer = $data['referrer'];
			unset( $data['referrer'] );
			$data['amount'] = $data['amount']/100;
			$data['error'] = $declinedDefault;
			
			$params = wfArrayToCGI( $data );
			$returnto = htmlspecialchars_decode( $referrer ) . '&' . $params;
			return $returnto;
		}
	}
	
}

// end class
