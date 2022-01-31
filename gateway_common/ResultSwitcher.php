<?php

abstract class ResultSwitcher extends GatewayPage {

	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = true;

	protected function handleRequest() {
		$this->handleResultRequest();
	}

	/**
	 * Whether or not the user comes back to the resultswitcher in an iframe
	 * @return bool true if we need to pop out of an iframe, otherwise false
	 */
	protected function isReturnFramed() {
		return false;
	}

	/**
	 * Render a resultswitcher page
	 */
	protected function handleResultRequest() {
		// no longer letting people in without these things. If this is
		// preventing you from doing something, you almost certainly want to be
		// somewhere else.
		$deadSession = false;
		if ( !$this->adapter->session_hasDonorData() ) {
			$deadSession = true;
		}
		$oid = $this->adapter->getData_Unstaged_Escaped( 'order_id' );

		$request = $this->getRequest();
		$referrer = $request->getHeader( 'referer' );
		$liberated = false;
		if ( $this->adapter->session_getData( 'order_status', $oid ) === 'liberated' ) {
			$liberated = true;
		}

		// XXX need to know whether we were in an iframe or not.
		global $wgServer;
		if ( $this->isReturnFramed() && ( strpos( $referrer, $wgServer ) === false ) && !$liberated ) {
			$sessionOrderStatus = $request->getSessionData( 'order_status' );
			$sessionOrderStatus[$oid] = 'liberated';
			$request->setSessionData( 'order_status', $sessionOrderStatus );
			$this->logger->info( "Resultswitcher: Popping out of iframe for Order ID " . $oid );
			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return;
		}

		$this->setHeaders();
		$userAgent = $request->getHeader( 'User-Agent' );
		if ( !$userAgent ) {
			$userAgent = 'Unknown';
		}

		if ( $this->isRepeatReturnProcess() ) {
			$this->logger->warning(
				'Donor is trying to process an already-processed payment. ' .
				"Adapter Order ID: $oid.\n" .
				"Cookies: " . print_r( $_COOKIE, true ) . "\n" .
				"User-Agent: " . $userAgent
			);
			$this->displayThankYouPage( 'repeat return processing' );
			return;
		}

		if ( $deadSession ) {
			if ( $this->adapter->isReturnProcessingRequired() ) {
				wfHttpError( 403, 'Forbidden', wfMessage( 'donate_interface-error-http-403' )->text() );
				throw new RuntimeException(
					'Resultswitcher: Request forbidden. No active donation in the session. ' .
					"Adapter Order ID: $oid.\n" .
					"Cookies: " . print_r( $_COOKIE, true ) . "\n" .
					"User-Agent: " . $userAgent
				);
			}
			// If it's possible for a donation to go through without our
			// having to do additional processing in the result switcher,
			// we don't want to falsely claim it failed just because we
			// lost the session data. We also don't want to give any
			// information to scammers hitting this page with no session,
			// so we always show the thank you page. We don't want to do
			// any post-processing if we're not sure whether we actually
			// originated this attempt, so we return right after.
			$this->logger->warning(
				'Resultswitcher: session is dead, but the ' .
				'donor may have made a successful payment.'
			);
			$this->displayThankYouPage( 'dead session' );
			return;
		}
		$this->logger->info( "Resultswitcher: OK to process Order ID: " . $oid );

		if ( $this->adapter->checkTokens() ) {
			// feed processDonorReturn all the GET and POST vars
			$requestValues = $this->getRequest()->getValues();
			$result = $this->adapter->processDonorReturn( $requestValues );
			$this->markReturnProcessed();
			$this->renderResponse( $result );
			return;
		} else {
			$this->logger->error( "Resultswitcher: Token Check Failed. Order ID: $oid" );
		}
		$this->displayFailPage();
	}

	protected function isRepeatReturnProcess() {
		$request = $this->getRequest();
		$requestProcessId = $this->adapter->getRequestProcessId(
			$request->getValues()
		);
		$key = 'processed_request-' . $requestProcessId;
		$cachedResult = ObjectCache::getLocalClusterInstance()->get( $key );
		return boolval( $cachedResult );
	}

	protected function markReturnProcessed() {
		$request = $this->getRequest();
		$requestProcessId = $this->adapter->getRequestProcessId(
			$request->getValues()
		);
		if ( !$requestProcessId ) {
			return;
		}
		$key = 'processed_request-' . $requestProcessId;

		// TODO: we could store the results of the last process here, but for now
		// we just indicate we did SOMETHING with it
		ObjectCache::getLocalClusterInstance()->add( $key, true, 7200 );
	}

	/**
	 * @param string $logReason Logged explanation for redirect
	 */
	protected function displayThankYouPage( $logReason ) {
		$thankYouPage = ResultPages::getThankYouPage( $this->adapter );
		$this->logger->info( "Displaying thank you page $thankYouPage for status $logReason." );
		$this->getOutput()->redirect( $thankYouPage );
	}

	public function setClientVariables( &$vars ) {
		parent::setClientVariables( $vars );
		if ( $this->adapter->showMonthlyConvert() ) {
			$vars['showMConStartup'] = true;
		}
	}
}
