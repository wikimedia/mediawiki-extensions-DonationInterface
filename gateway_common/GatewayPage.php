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
use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\FinalStatus;

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
	 * Derived classes must override this with the identifier of the gateway
	 * as set in GatewayAdapter::IDENTIFIER
	 * @var string
	 */
	protected $gatewayIdentifier;

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
	 * @param string|null $par parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgContributionTrackingFundraiserMaintenance, $wgContributionTrackingFundraiserMaintenanceUnsched;

		// FIXME: Deprecate "language" param.
		$language = $this->getRequest()->getVal( 'language' );
		if ( !$language ) {
			// For some result pages, language does not come in on a standard URL param
			// (langauge or uselang). For those cases, it's pretty safe to assume the
			// correct language is in session.
			// FIXME Restrict the places where we access session data
			$donorData = WmfFramework::getSessionValue( 'Donor' );
			if ( ( $donorData !== null ) && isset( $donorData[ 'language' ] ) ) {
				$language = $donorData[ 'language' ];
			}
		}

		if ( $language ) {
			$this->getContext()->setLanguage( $language );
		}

		if ( $wgContributionTrackingFundraiserMaintenance
			|| $wgContributionTrackingFundraiserMaintenanceUnsched ) {
			$this->getOutput()->redirect( Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL(), '302'
			);
			return;
		}

		$gatewayName = $this->getGatewayIdentifier();
		$className = DonationInterface::getAdapterClassForGateway( $gatewayName );
		DonationInterface::setSmashPigProvider( $gatewayName );

		try {
			$variant = $this->getVariant();
			$this->adapter = new $className( [ 'variant' => $variant ] );
			$this->overrideLogo();
			$this->logger = DonationLoggerFactory::getLogger( $this->adapter );

			// FIXME: SmashPig should just use Monolog.
			Logger::getContext()->enterContext( $this->adapter->getLogMessagePrefix() );

			$out = $this->getOutput();
			$out->preventClickjacking();
			$out->addModuleStyles( 'donationInterface.styles' );
			$out->addModules( 'donationInterface.skinOverride' );
			// Stolen from Minerva skin
			$out->addHeadItem( 'viewport',
				Html::element(
					'meta', [
						'name' => 'viewport',
						'content' => 'initial-scale=1.0, user-scalable=yes, minimum-scale=0.25, maximum-scale=5.0, width=device-width',
					]
				)
			);

		} catch ( Exception $ex ) {
			if ( !$this->logger ) {
				$this->logger = DonationLoggerFactory::getLoggerForType(
					$className,
					$this->getLogPrefix()
				);
			}
			$this->logger->error(
				"Exception setting up GatewayPage with adapter class $className: " .
					"{$ex->getMessage()}\n{$ex->getTraceAsString()}"
			);
			// Setup scrambled, no point in continuing
			$this->displayFailPage();
			return;
		}

		// FIXME: Should have checked this before creating the adapter.
		if ( $this->adapter->getGlobal( 'Enabled' ) !== true ) {
			$this->logger->info( 'Displaying fail page for disabled gateway' );
			$this->displayFailPage();
			return;
		}

		if ( $this->adapter->getFinalStatus() === FinalStatus::FAILED ) {
			$this->logger->info( 'Displaying fail page for failed GatewayReady checks' );
			$this->displayFailPage();
			return;
		}

		Hooks::register( 'MakeGlobalVariablesScript', [ $this, 'setClientVariables' ] );

		try {
			$this->handleRequest();
		} catch ( Exception $ex ) {
			$this->logger->error( "Displaying fail page for exception: " . $ex->getMessage() );
			$this->displayFailPage();
			return;
		}
	}

	/**
	 * Handle the donation request.
	 *
	 * FIXME: Be more disciplined about how handleRequest fits with
	 * handleDonationRequest.  Would it be cleaner to move to a pre and post
	 * hook scheme?
	 */
	protected function handleRequest() {
		$this->handleDonationRequest();
	}

	/**
	 * Build and display form to user
	 */
	public function displayForm() {
		$output = $this->getOutput();

		$form_class = $this->adapter->getFormClass();
		// TODO: use interface.  static ctor.
		if ( $form_class && class_exists( $form_class ) ) {
			$form_obj = new $form_class();
			$form_obj->setGateway( $this->adapter );
			$form_obj->setGatewayPage( $this );
			$form = $form_obj->getForm();
			$output->addModules( $form_obj->getResources() );
			$output->addModuleStyles( $form_obj->getStyleModules() );
			$output->addHTML( $form );
		} else {
			$this->logger->error( "Displaying fail page for bad form class '$form_class'" );
			$this->displayFailPage( false );
		}
	}

	/**
	 * Display a failure page
	 *
	 * @param bool $allowRapid Whether to allow rendering a RapidFail form
	 *  renderForm sets this to false on failure to avoid an infinite loop
	 */
	public function displayFailPage( $allowRapid = true ) {
		$output = $this->getOutput();

		if ( $this->adapter && $allowRapid ) {
			$page = ResultPages::getFailPage( $this->adapter );
			// FIXME: Structured data $page rather than a union.  displayForm
			// will add the ffname if needed.
			if ( !filter_var( $page, FILTER_VALIDATE_URL ) ) {
				// If it's not a URL, we're rendering a RapidFail form
				$this->logger->info( "Displaying fail form $page" );
				$this->adapter->addRequestData( [ 'ffname' => $page ] );
				$this->displayForm();
				return;
			}
		} else {
			$gatewayName = $this->getGatewayIdentifier();
			$className = DonationInterface::getAdapterClassForGateway( $gatewayName );
			$page = ResultPages::getFailPageForType(
				$className,
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
	 * Override if your page selects between multiple adapters based on
	 * context.
	 */
	protected function getGatewayIdentifier() {
		return $this->gatewayIdentifier;
	}

	/**
	 * displayResultsForDebug
	 *
	 * Displays useful information for debugging purposes.
	 * Enable with $wgDonationInterfaceDisplayDebug, or the adapter equivalent.
	 * @param PaymentTransactionResponse|null $results
	 * @return null
	 */
	protected function displayResultsForDebug( PaymentTransactionResponse $results = null ) {
		$results = empty( $results ) ? $this->adapter->getTransactionResponse() : $results;

		if ( $this->adapter->getGlobal( 'DisplayDebug' ) !== true ) {
			return;
  }

		$output = $this->getOutput();

		$output->addHTML( Html::element( 'span', null, $results->getMessage() ) );

		$errors = $results->getErrors();
		if ( !empty( $errors ) ) {
			$output->addHTML( Html::openElement( 'ul' ) );
			foreach ( $errors as $code => $value ) {
				$output->addHTML( Html::element( 'li', null, "Error $code: " . print_r( $value, true ) ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		}

		$data = $results->getData();
		if ( !empty( $data ) ) {
			$output->addHTML( Html::openElement( 'ul' ) );
			foreach ( $data as $key => $value ) {
				if ( is_array( $value ) ) {
					$output->addHTML( Html::openElement( 'li', null ) . Html::openElement( 'ul' ) );
					foreach ( $value as $key2 => $val2 ) {
						$output->addHTML( Html::element( 'li', null, "$key2: $val2" ) );
					}
					$output->addHTML( Html::closeElement( 'ul' ) . Html::closeElement( 'li' ) );
				} else {
					$output->addHTML( Html::element( 'li', null, "$key: $value" ) );
				}
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "Empty Results" );
		}
		$donorData = $this->getRequest()->getSessionData( 'Donor' );
		if ( is_array( $donorData ) ) {
			$output->addHTML( "Session Donor Vars:" . Html::openElement( 'ul' ) );
			foreach ( $donorData as $key => $val ) {
				$output->addHTML( Html::element( 'li', null, "$key: $val" ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "No Session Donor Vars:" );
		}

		if ( is_array( $this->adapter->debugarray ) ) {
			$output->addHTML( "Debug Array:" . Html::openElement( 'ul' ) );
			foreach ( $this->adapter->debugarray as $val ) {
				$output->addHTML( Html::element( 'li', null, $val ) );
			}
			$output->addHTML( Html::closeElement( 'ul' ) );
		} else {
			$output->addHTML( "No Debug Array" );
		}
	}

	/**
	 * Respond to a donation request
	 */
	protected function handleDonationRequest() {
		$this->setHeaders();

		// TODO: This is where we should feed GPCS parameters into the gateway
		// and DonationData, rather than harvest params in the adapter itself.

		// dispatch forms/handling
		if ( $this->adapter->checkTokens() ) {
			if ( $this->isProcessImmediate() ) {
				// Check form for errors
				$validated_ok = $this->adapter->validatedOK();

				// Proceed to the next step, unless there were errors.
				if ( $validated_ok ) {
					// Attempt to process the payment, then render the response.
					$this->processPayment();
				} else {
					// Redisplay form to give the donor notification and a
					// chance correct their errors.
					$this->displayForm();
				}
			} else {
				$this->adapter->session_addDonorData();
				$this->displayForm();
			}
		} else { // token mismatch
			$this->adapter->getErrorState()->addError( new PaymentError(
				'internal-0001',
				'Failed CSRF token validation',
				LogLevel::INFO
			) );
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
	 * Ask the adapter to perform a payment
	 *
	 * Route the donor based on the response.
	 */
	protected function processPayment() {
		$this->renderResponse( $this->adapter->doPayment() );
	}

	/**
	 * Take UI action suggested by the payment result
	 * @param PaymentResult $result returned by gateway adapter's doPayment
	 *  or processDonorReturn function
	 */
	protected function renderResponse( PaymentResult $result ) {
		if ( $result->isFailed() ) {
			$this->logger->info( 'Displaying fail page for failed PaymentResult' );
			$this->displayFailPage();
		} elseif ( $url = $result->getRedirect() ) {
			$this->adapter->logPending();
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

			$this->adapter->addRequestData( [
				'ffname' => $form,
			] );
			$this->displayForm();
		} elseif (
			count( $result->getErrors() )
		) {
			$this->displayForm();
		} elseif ( $this->adapter->showMonthlyConvert() ) {
			$this->logger->info( "PaymentResult successful, now asking for a recurring donation." );
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
		$attrs = [
			'id' => 'paymentiframe',
			'name' => 'paymentiframe',
			'width' => '680',
			'height' => '300'
		];

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
	 * @return string
	 */
	protected function getLogPrefix() {
		$info = [];
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
		// Maybe ask $form_obj for a title so different errors can show different titles
		$this->getOutput()->setPageTitle( wfMessage( 'donate_interface-make-your-donation' ) );
	}

	/**
	 * MakeGlobalVariablesScript handler, sends settings to Javascript
	 * @param array &$vars
	 */
	public function setClientVariables( &$vars ) {
		$language = $this->adapter->getData_Unstaged_Escaped( 'language' );
		$country = $this->adapter->getData_Unstaged_Escaped( 'country' );
		$vars['wgDonationInterfacePriceFloor'] = $this->adapter->getGlobal( 'PriceFloor' );
		$vars['wgDonationInterfacePriceCeiling'] = $this->adapter->getGlobal( 'PriceCeiling' );
		try {
			$clientRules = $this->adapter->getClientSideValidationRules();
			if ( !empty( $clientRules ) ) {
				// Translate all the messages
				// FIXME: figure out country fallback add the i18n strings
				// for use with client-side mw.msg()
				foreach ( $clientRules as &$fieldRules ) {
					foreach ( $fieldRules as &$rule ) {
						if ( !empty( $rule['messageKey'] ) ) {
							$rule['message'] = MessageUtils::getCountrySpecificMessage(
								$rule['messageKey'],
								$country,
								$language
							);
						}
					}
				}
				$vars['wgDonationInterfaceValidationRules'] = $clientRules;
			}
		} catch ( Exception $ex ) {
			$this->logger->warning(
				'Caught exception setting client-side validation rules: ' .
				$ex->getMessage()
			);
		}
	}

	/**
	 * If certain conditions are met, override the logo
	 */
	protected function overrideLogo() {
		$logoOverrideRules = $this->adapter->getGlobal( 'LogoOverride' );
		if ( !$logoOverrideRules ) {
			return;
		}
		$request = $this->getRequest();
		// If an override is stored in session, use that
		$storedOverride = $request->getSessionData( 'logoOverride' );
		if ( !$storedOverride ) {
			foreach ( $logoOverrideRules as $rule ) {
				$variableName = $rule['variable'];
				$value = $rule['value'];
				$actualValue = $this->getRequest()->getVal( $variableName );
				if ( $value === $actualValue ) {
					$storedOverride = [
						'logo' => $rule['logo'],
						'logoHD' => $rule['logoHD']
					];
					$request->setSessionData( 'logoOverride', $storedOverride );
					break;
				}
			}
		}

		if ( $storedOverride ) {
			// need to keep generated CSS in sync with
			// @see ResourceLoaderSkinModule::getStyles
			// html body is prepended to give us more-specific selectors
			$css = 'html body .mw-wiki-logo { background-image: url(' .
				$storedOverride['logo'] . "); }\n";
			if (
				!empty( $storedOverride['logoHD'] ) &&
				!empty( $storedOverride['logoHD']['1.5x'] )
			) {
				$css .= '@media (-webkit-min-device-pixel-ratio: 1.5), ' .
					'(min--moz-device-pixel-ratio: 1.5), (min-resolution: ' .
					'1.5dppx), (min-resolution: 144dpi) { html body ' .
					'.mw-wiki-logo { background-image: url(' .
					$storedOverride['logoHD']['1.5x'] .
					");background-size: 135px auto; }}\n";
			}
			if (
				!empty( $storedOverride['logoHD'] ) &&
				!empty( $storedOverride['logoHD']['2x'] )
			) {
				$css .= '@media (-webkit-min-device-pixel-ratio: 2), ' .
					'(min--moz-device-pixel-ratio: 2), (min-resolution: ' .
					'2dppx), (min-resolution: 192dpi) { html body ' .
					'.mw-wiki-logo { background-image: url(' .
					$storedOverride['logoHD']['2x'] .
					");background-size: 135px auto; }}\n";
			}
			$this->getOutput()->addInlineStyle( $css );
		}
	}

	protected function getVariant() {
		// FIXME: This is the sort of thing DonationData is supposed to do,
		// but we construct it too late to use variant in the configuration
		// reader. We should be pulling all the get / post / session variables
		// up here in the page class before creating the adapter.
		$variant = $this->getRequest()->getVal( 'variant' );
		if ( !$variant ) {
			$donorData = $this->getRequest()->getSessionData( 'Donor' );
			if ( $donorData && !empty( $donorData['variant'] ) ) {
				$variant = $donorData['variant'];
			}
		}
		return $variant;
	}
}
