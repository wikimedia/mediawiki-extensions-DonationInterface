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
 * *** Constraint for implementing classes *** The special page name must always be the gateway
 * adapter class name with 'Adapter' replaced with 'Gateway'.
 */
abstract class GatewayPage extends UnlistedSpecialPage {
	/**
	 * flag for setting Monthly Convert modal on template
	 * @var bool
	 */
	public $supportsMonthlyConvert = false;

	/**
	 * Derived classes must override this with the identifier of the gateway
	 * as set in GatewayAdapter::IDENTIFIER
	 * @var string
	 */
	protected $gatewayIdentifier;

	/**
	 * The gateway adapter object
	 * @var GatewayAdapter
	 */
	public $adapter;

	/**
	 * Gateway-specific logger
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * When true, display an error form rather than the standard payment form
	 * @var bool
	 */
	protected $showError = false;

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
		// FIXME: Deprecate "language" param.
		$language = $this->getRequest()->getVal( 'language' );
		$this->showError = $this->getRequest()->getBool( 'showError' );

		if ( !$language ) {
			// For some result pages, language does not come in on a standard URL param
			// (language or uselang). For those cases, it's pretty safe to assume the
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

		if ( $this->getConfig()->get( 'DonationInterfaceFundraiserMaintenance' ) ) {
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
			$this->logger = DonationLoggerFactory::getLogger( $this->adapter );

			// FIXME: SmashPig should just use Monolog.
			Logger::getContext()->enterContext( $this->adapter->getLogMessagePrefix() );

			$out = $this->getOutput();
			$out->preventClickjacking();
			// Use addModuleStyles to load these CSS rules in early and avoid
			// a flash of MediaWiki elements.
			$out->addModuleStyles( 'donationInterface.styles' );
			$out->addModuleStyles( 'donationInterface.skinOverrideStyles' );

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
		if ( $this->showError ) {
			$form = new MustacheErrorForm();
		} else {
			$form = new Gateway_Form_Mustache();
		}
		$form->setGateway( $this->adapter );
		$form->setGatewayPage( $this );

		$formHtml = $form->getForm();

		if ( !$this->showError ) {
			// Only register the setClientVariables callback if we're loading a real form.
			// Error forms don't load any gateway-specific scripts so don't need these variables.
			// And if we're already in an error condition (like an invalid payment method),
			// calling setClientVariables could throw an exception. We set this hook after
			// $form->getForm has succeeded so we avoid registering it when that function
			// throws an error
			$this->getHookContainer()->register(
				'MakeGlobalVariablesScript', [ $this, 'setClientVariables' ]
			);
		}

		$output = $this->getOutput();
		$output->addModules( $form->getResources() );
		$output->addModuleStyles( $form->getStyleModules() );
		$output->addHTML( $formHtml );
	}

	/**
	 * Display a failure page
	 */
	public function displayFailPage() {
		if ( $this->adapter ) {
			$this->showError = true;
			$this->displayForm();
		} else {
			$output = $this->getOutput();
			$output->prepareErrorPage( $this->msg( 'donate_interface-error-msg-general' ) );
			$output->addHTML( $this->msg(
				'donate_interface-otherways',
				[ $this->getConfig()->get( 'DonationInterfaceOtherWaysURL' ) ]
			)->plain() );
		}
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
		} elseif (
			count( $result->getErrors() )
		) {
			$this->displayForm();
		} elseif ( $this->adapter->showMonthlyConvert() ) {
			$this->logger->info( "Displaying monthly convert modal after successful one-time donation PaymentResult" );
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
		$this->getOutput()->setPageTitle( $this->msg( 'donate_interface-make-your-donation' ) );
	}

	/**
	 * MakeGlobalVariablesScript handler, sends settings to Javascript
	 * @param array &$vars
	 */
	public function setClientVariables( &$vars ) {
		$language = $this->adapter->getData_Unstaged_Escaped( 'language' );
		$country = $this->adapter->getData_Unstaged_Escaped( 'country' );
		$vars['wgDonationInterfaceAmountRules'] = $this->adapter->getDonationRules();
		$vars['wgDonationInterfaceLogDebug'] = $this->adapter->getGlobal( 'LogDebug' );
		if ( $this->adapter->showMonthlyConvert() ) {
			$thankYouUrl = ResultPages::getThankYouPage( $this->adapter );
			$vars['wgDonationInterfaceThankYouUrl'] = $thankYouUrl;
			$vars['showMConStartup'] = $this->getRequest()->getBool( 'debugMonthlyConvert' );
			$vars['wgDonationInterfaceMonthlyConvertAmounts'] = $this->adapter->getMonthlyConvertAmounts();
		}

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

	/**
	 * Integrations that do not show submethod buttons should override to return false.
	 *
	 * @return bool
	 */
	public function showSubmethodButtons() {
		return true;
	}

	/**
	 * Integrations that never need a continue button should override to return false.
	 *
	 * @return bool
	 */
	public function showContinueButton() {
		return true;
	}

	/**
	 * Get the name of the special page for a gateway.
	 *
	 * @param string $gatewayId
	 * @param Config $mwConfig MediaWiki Config
	 * @return string
	 */
	public static function getGatewayPageName( string $gatewayId, Config $mwConfig ): string {
		$gatewayClasses = $mwConfig->get( 'DonationInterfaceGatewayAdapters' );

		// T302939: in order to pass the SpecialPageFatalTest::testSpecialPageDoesNotFatal unit test
		// since no aliases are defined for those TestingAdapters
		// will remove below if condition once those TestingAdapter gone from the test cases
		if ( str_starts_with( $gatewayClasses[ $gatewayId ], 'Testing' ) ) {
			$specialPage = 'GatewayChooser';
		} else {
			// The special page name is the gateway adapter class name with 'Adapter'
			// replaced with 'Gateway'.
			$specialPage = str_replace(
				'Adapter',
				'Gateway',
				$gatewayClasses[ $gatewayId ]
			);
		}

		return $specialPage;
	}
}
