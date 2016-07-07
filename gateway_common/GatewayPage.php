<?php
/**
 * Wikimedia Foundation
 *
 * LICENSE
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 */

/**
 * GatewayPage
 * This class is the generic unlisted special page in charge of actually 
 * displaying the form. Each gateway will have one or more direct descendants of 
 * this class, with most of the gateway-specific control logic in its handleRequest
 * function. For instance: extensions/DonationInterface/globalcollect_gateway/globalcollect_gateway.body.php
 *
 */
abstract class GatewayPage extends UnlistedSpecialPage {

	/**
	 * Derived classes must override this with the name of the gateway
	 * adapter class to use in this page, or override the getAdapterClass
	 * function.
	 * @var string
	 */
	protected $adapterClass;

	/**
	 * An array of form errors
	 * @var array $errors
	 */
	public $errors = array( );

	/**
	 * The gateway adapter object
	 * @var GatewayAdapter $adapter
	 */
	public $adapter;

	/**
	 * Gateway-specific logger
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$me = get_called_class();
		parent::__construct( $me );
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgContributionTrackingFundraiserMaintenance, $wgContributionTrackingFundraiserMaintenanceUnsched;

		// FIXME: Deprecate "language" param.
		$language = $this->getRequest()->getVal( 'language' );
		if ( $language ) {
			$this->getContext()->setLanguage( $language );
			global $wgLang;
			$wgLang = $this->getContext()->getLanguage(); // BackCompat
		}

		try {
			$className = $this->getAdapterClass();
			$this->adapter = new $className;
			$this->logger = DonationLoggerFactory::getLogger( $this->adapter );
			$this->getOutput()->addModuleStyles( 'donationInterface.styles' );
			$this->getOutput()->addModules( 'donationInterface.skinOverride' );
		} catch ( Exception $ex ) {
			if ( !$this->logger ) {
				$this->logger = DonationLoggerFactory::getLoggerForType(
					$this->getAdapterClass(),
					$this->getLogPrefix()
				);
			}
			$this->logger->error(
				"Exception setting up GatewayPage with adapter class {$this->getAdapterClass()}: " .
				    "{$ex->getMessage()}\n{$ex->getTraceAsString()}"
			);
			// Setup scrambled, no point in continuing
			$this->displayFailPage();
			return;
		}

		if ( $this->adapter->getGlobal( 'Enabled' ) !== true ) {
			$this->logger->info( 'Displaying fail page for disabled gateway' );
			$this->displayFailPage();
			return;
		}

		if( $wgContributionTrackingFundraiserMaintenance
			|| $wgContributionTrackingFundraiserMaintenanceUnsched ){
			$this->getOutput()->redirect( Title::newFromText('Special:FundraiserMaintenance')->getFullURL(), '302' );
			return;
		}

		Hooks::register( 'MakeGlobalVariablesScript', array( $this->adapter, 'setClientVariables' ) );

		try {
			$this->handleRequest();
		} catch ( Exception $ex ) {
			$this->logger->error( "Displaying fail page for exception: " . $ex->getMessage() );
			$this->displayFailPage();
			return;
		}
	}

	/**
	 * Should be overridden in each derived class to actually handle the request
	 * Performs gateway-specific checks and either redirects or displays form.
	 */
	protected abstract function handleRequest();

	/**
	 * Checks current dataset for validation errors
	 * TODO: As with every other bit of gateway-related logic that should 
	 * definitely be available to every entry point, and functionally has very 
	 * little to do with being contained within what in an ideal world would be 
	 * a piece of mostly UI, this function needs to be moved inside the gateway 
	 * adapter class.
	 *
	 * @return boolean Returns false on an error-free validation, otherwise true.
	 * FIXME: that return value seems backwards to me.
	 */
	public function validateForm() {

		$validated_ok = $this->adapter->revalidate();

		return !$validated_ok;
	}

	/**
	 * Build and display form to user
	 */
	public function displayForm() {
		$output = $this->getOutput();

		$form_class = $this->getFormClass();
		// TODO: use interface.  static ctor.
		if ( $form_class && class_exists( $form_class ) ){
			$form_obj = new $form_class( $this->adapter );
			$form = $form_obj->getForm();
			$output->addModules( $form_obj->getResources() );
			$output->addModuleStyles( $form_obj->getStyleModules() );
			$output->addHTML( $form );
		} else {
			$this->logger->error( "Displaying fail page for bad form class '$form_class'" );
			$this->displayFailPage();
		}
	}

	/**
	 * Display a generic failure page
	 */
	public function displayFailPage() {
		$output = $this->getOutput();

		if ( $this->adapter ) {
			$page = ResultPages::getFailPage( $this->adapter );
		} else {
			$page = ResultPages::getFailPageForType(
				$this->getAdapterClass(),
				$this->getLogPrefix()
			);
		}
		$log_message = "Redirecting to [{$page}]";
		$this->logger->info( $log_message );

		$output->redirect( $page );
	}

	/**
	 * Get the current adapter class
	 * @return string containing the chosen adapter class name
	 *
	 * Override if your gateway selects between multiple adapters based on
	 * context.
	 */
	protected function getAdapterClass() {
		return $this->adapterClass;
	}

	/**
	 * Get the currently set form class
	 * @return mixed string containing the valid and enabled form class, otherwise false. 
	 */
	public function getFormClass() {
		return $this->adapter->getFormClass();
	}

	/**
	 * displayResultsForDebug
	 *
	 * Displays useful information for debugging purposes.
	 * Enable with $wgDonationInterfaceDisplayDebug, or the adapter equivalent.
	 * @param PaymentTransactionResponse $results
	 * @return null
	 */
	protected function displayResultsForDebug( PaymentTransactionResponse $results = null ) {

		$results = empty( $results ) ? $this->adapter->getTransactionResponse() : $results;
		
		if ( $this->adapter->getGlobal( 'DisplayDebug' ) !== true ){
			return;
		}

		$output = $this->getOutput();

		$output->addHTML( Html::element( 'span', null, $results->getMessage() ) );

		$errors = $results->getErrors();
		if ( !empty( $errors ) ) {
			$output->addHTML( Html::openElement( 'ul' ) );
			foreach ( $errors as $code => $value ) {
				$output->addHTML( Html::element('li', null, "Error $code: " . print_r( $value, true ) ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		}

		$data = $results->getData();
		if ( !empty( $data ) ) {
			$output->addHTML( Html::openElement( 'ul' ) );
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$output->addHTML( Html::openElement('li', null, $key ) . Html::openElement( 'ul' ) );
					foreach ( $value as $key2 => $val2 ) {
						$output->addHTML( Html::element('li', null, "$key2: $val2" ) );
					}
					$output->addHTML( Html::closeElement( 'ul' ) . Html::closeElement( 'li' ) );
				} else {
					$output->addHTML( Html::element('li', null, "$key: $value" ) );
				}
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "Empty Results" );
		}
		$donorData = $this->getRequest()->getSessionData( 'Donor' );
		if ( is_array( $donorData ) ) {
			$output->addHTML( "Session Donor Vars:" . Html::openElement( 'ul' ));
			foreach ( $donorData as $key => $val ) {
				$output->addHTML( Html::element('li', null, "$key: $val" ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "No Session Donor Vars:" );
		}

		if ( is_array( $this->adapter->debugarray ) ) {
			$output->addHTML( "Debug Array:" . Html::openElement( 'ul' ) );
			foreach ( $this->adapter->debugarray as $val ) {
				$output->addHTML( Html::element('li', null, $val ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "No Debug Array" );
		}
	}

	/**
	 * Fetch the array of iso country codes => country names
	 * @return array
	 */
	public static function getCountries() {
		return CountryCodes::getCountryCodes();
	}

	/**
	 * Respond to a donation request
	 */
	protected function handleDonationRequest() {
		$this->setHeaders();

		// TODO: this is where we should feed GPCS parameters into DonationData.

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			if ( $this->isProcessImmediate() ) {
				// Check form for errors
				// FIXME: Should this be rolled into adapter.doPayment?
				$form_errors = $this->validateForm();

				// If there were errors, redisplay form, otherwise proceed to next step
				if ( $form_errors ) {
					$this->displayForm();
				} else {
					// Attempt to process the payment, and render the response.
					$this->processPayment();
				}
			} else {
				$this->adapter->session_addDonorData();
				$this->displayForm();
			}
		} else { //token mismatch
			$error['general']['token-mismatch'] = $this->msg( 'donate_interface-token-mismatch' );
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}

	/**
	 * Determine if we should attempt to process the payment now
	 *
	 * @return bool True if we should attempt processing.
	 */
	protected function isProcessImmediate() {
		// If the user posted to this form, process immediately.
		if ( $this->adapter->posted ) {
			return true;
		}

		// Otherwise, respect the "redirect" parameter.  If it is "1", try to
		// skip the interstitial page.  If it's "0", do not process immediately.
		$redirect = $this->adapter->getData_Unstaged_Escaped( 'redirect' );
		if ( $redirect !== null ) {
			return ( $redirect === '1' || $redirect === 'true' );
		}

		return false;
	}

	/**
	 * Whether or not the user comes back to the resultswitcher in an iframe
	 * @return boolean true if we need to pop out of an iframe, otherwise false
	 */
	protected function isReturnFramed() {
		return false;
	}

	/**
	 * Render a resultswitcher page
	 */
	protected function handleResultRequest() {
		//no longer letting people in without these things. If this is
		//preventing you from doing something, you almost certainly want to be
		//somewhere else.
		$forbidden = false;
		if ( !$this->adapter->session_hasDonorData() ) {
			$forbidden = true;
			$f_message = 'No active donation in the session';
		}

		if ( $forbidden ) {
			wfHttpError( 403, 'Forbidden', wfMessage( 'donate_interface-error-http-403' )->text() );
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
			//TODO: Move the $forbidden check back to the beginning of this if block, once we know this doesn't happen a lot.
			//TODO: If we get a lot of these messages, we need to redirect to something more friendly than FORBIDDEN, RAR RAR RAR.
			if ( $forbidden ) {
				$this->logger->error( "Resultswitcher: $oid SHOULD BE FORBIDDEN. Reason: $f_message" );
			}
			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return;
		}

		$this->setHeaders();

		if ( $forbidden ){
			throw new RuntimeException( "Resultswitcher: Request forbidden. " . $f_message . " Adapter Order ID: $oid" );
		}
		$this->logger->info( "Resultswitcher: OK to process Order ID: " . $oid );

		if ( $this->adapter->checkTokens() ) {
			$this->getOutput()->allowClickjacking();
			// FIXME: do we really need this again?
			$this->getOutput()->addModules( 'iframe.liberator' );
			// processResponse expects some data, so let's feed it all the
			// GET and POST vars
			$response = $this->getRequest()->getValues();
			// TODO: run the whole set of getResponseStatus, getResponseErrors
			// and getResponseData first.  Maybe do_transaction with a
			// communication_type of 'incoming' and a way to provide the
			// adapter the GET/POST params harvested here.
			$this->adapter->processResponse( $response );
			$status = $this->adapter->getFinalStatus();
			switch ( $status ) {
			case FinalStatus::COMPLETE:
			case FinalStatus::PENDING:
				$thankYouPage = ResultPages::getThankYouPage( $this->adapter );
				$this->logger->info( "Displaying thank you page $thankYouPage for status $status." );
				$this->getOutput()->redirect( $thankYouPage );
				return;
			}
			$this->logger->info( "Displaying fail page for final status $status" );
		} else {
			$this->logger->error( "Resultswitcher: Token Check Failed. Order ID: $oid" );
		}
		$this->displayFailPage();
	}

	/**
	 * Ask the adapter to perform a payment
	 *
	 * Route the donor based on the response.
	 */
	protected function processPayment() {
		$this->renderResponse( $this->adapter->doPayment() );
	}

	/**
	 * Take UI action suggested by the payment result
	 */
	protected function renderResponse( PaymentResult $result ) {
		if ( $result->isFailed() ) {
			$this->logger->info( 'Displaying fail page for failed PaymentResult' );
			$this->displayFailPage();
		} elseif ( $url = $result->getRedirect() ) {
			$this->getOutput()->redirect( $url );
		} elseif ( $url = $result->getIframe() ) {
			// Show a form containing an iframe.

			// Well, that's sketchy.  See TODO in renderIframe: we should
			// accomplish this entirely by passing an iframeSrcUrl parameter
			// to the template.
			$this->displayForm();

			$this->renderIframe( $url );
		} elseif ( $form = $result->getForm() ) {
			// Show another form.

			$this->adapter->addRequestData( array(
				'ffname' => $form,
			) );
			$this->displayForm();
		} elseif ( $errors = $result->getErrors() ) {
			// FIXME: Creepy.  Currently, the form inspects adapter errors.  Use
			// the stuff encapsulated in PaymentResult instead.
			foreach ( $this->adapter->getTransactionResponse()->getErrors() as $code => $transactionError ) {
				$message = $transactionError['message'];
				$error = array();
				if ( !empty( $transactionError['context'] ) ) {
					$error[$transactionError['context']] = $message;
				} else if ( strpos( $code, 'internal' ) === 0 ) {
					$error['retryMsg'][ $code ] = $message;
				}
				else {
					$error['general'][ $code ] = $message;
				}
				$this->adapter->addManualError( $error );
			}
			$this->displayForm();
		} else {
			// Success.
			$thankYouPage = ResultPages::getThankYouPage( $this->adapter );
			$this->logger->info( "Displaying thank you page $thankYouPage for successful PaymentResult." );
			$this->getOutput()->redirect( $thankYouPage );
		}
	}

	/**
	 * Append iframe
	 *
	 * TODO: Should be rendered by the template.
	 *
	 * @param string $url
	 */
	protected function renderIframe( $url ) {
		$attrs = array(
			'id' => 'paymentiframe',
			'name' => 'paymentiframe',
			'width' => '680',
			'height' => '300'
		);

		$attrs['frameborder'] = '0';
		$attrs['style'] = 'display:block;';
		$attrs['src'] = $url;
		$paymentFrame = Xml::openElement( 'iframe', $attrs );
		$paymentFrame .= Xml::closeElement( 'iframe' );

		$this->getOutput()->addHTML( $paymentFrame );
	}

	/**
	 * Try to get donor information to tag log entries in case we don't
	 * have an adapter instance.
	 */
	protected function getLogPrefix() {
		$info = array();
		$donorData = $this->getRequest()->getSessionData( 'Donor' );
		if ( is_array( $donorData ) ) {
			if ( isset( $donorData['contribution_tracking_id'] ) ) {
				$info[] = $donorData['contribution_tracking_id'];
			}
			if ( isset( $donorData['order_id'] ) ) {
				$info[] = $donorData['order_id'];
			}
		}
		return implode( ':', $info ) . ' ';
	}

	public function setHeaders() {
		parent::setHeaders();

		// TODO: Switch title according to failiness.
		$this->getOutput()->setPageTitle( wfMessage( 'donate_interface-make-your-donation' ) );
	}
}
