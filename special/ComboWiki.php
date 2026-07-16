<?php

namespace MediaWiki\Extension\DonationInterface\Special;

use CountryValidation;
use DonationInterface;
use DonationLoggerFactory;
use GravyAdapter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;
use ResultPages;

/**
 * ComboWiki: the single-page VueJS donation flow.
 *
 * Special page that sets up the ComboWiki Vue application.
 * It loads the Vue + styles ResourceLoader modules, sets up the
 * viewport, and exposes server-side configuration to the client through the
 * MakeGlobalVariablesScript hook using setClientVariables().
 */
class ComboWiki extends UnlistedSpecialPage {

	private LoggerInterface $logger;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter', 'ComboWiki' );
		parent::__construct( 'ComboWiki' );
	}

	/**
	 * @param string|null $subPage
	 *
	 * @return void
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->setPageTitleMsg( $this->msg( 'combowiki-title' ) );

		// Expose server-side config to the Vue app.
		$this->getHookContainer()->register(
			'MakeGlobalVariablesScript',
			[
				$this,
				'setClientVariables'
			]
		);

		$this->addStylesScriptsAndViewport();
	}

	/**
	 * @return void
	 */
	public function addStylesScriptsAndViewport(): void {
		$out = $this->getOutput();

		$context = RequestContext::getMain();
		$assetsPath = $context->getConfig()->get( 'ScriptPath' ) .
			'/extensions/DonationInterface/modules/ext.donationInterface.comboWiki/assets';

		// Adding styles-only modules this way causes them to arrive ahead of page rendering.
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.comboWikiStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.comboWiki'
		] );

		$out->addJsConfigVars( [
			'assets_path' => $assetsPath
		] );

		$out->addHeadItem(
			'viewport',
			Html::element(
				'meta',
				[
					'name' => 'viewport',
					'content' => 'width=device-width, initial-scale=1',
				]
			)
		);

		$out->addLink( [
			'rel' => 'dns-prefetch',
			'href' => 'https://upload.wikimedia.org'
		] );
	}

	/**
	 * Set variables to be read in client-side JS code.
	 *
	 * @param array &$vars
	 *
	 * @return void
	 */
	public function setClientVariables( array &$vars ): void {
		$params = $this->getRoutingParams();
		$selectedGateway = $this->chooseGateway( $params );

		$vars['comboWiki'] = [
			'language' => $this->getLanguage()->getCode(),
			'params' => $params,
			'gateway' => $selectedGateway
		];

		// TODO: move this to a central decision point
		if ( $selectedGateway === 'gravy' ) {
			$this->addGravyClientConfig( $vars, $params );
		}
	}

	private function getRoutingParams(): array {
		$country = $this->getRequest()->getVal( 'country' );
		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			$country = CountryValidation::lookUpCountry( $this->getRequest()->getIP() );
		}

		// GeoIP can't resolve some IPs (e.g. localhost), leaving us with no
		// country. DonationData falls back to 'XX' here but let's use 'US' instead
		// so we have a real country as the fallback which can be passed to gateway chooser.
		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			$country = 'US';
		}

		$recurringRawValue = $this->getRequest()->getVal( 'recurring' );
		if ( $recurringRawValue === 'false' ) {
			$recurring = 0;
		} elseif ( $recurringRawValue === 'true' ) {
			$recurring = 1;
		} else {
			$recurring = (int)$recurringRawValue;
		}

		// ComboWiki lets the donor pick a method in the page, so payment_method
		// may be absent on initial load. Default to the card method ('cc'), the
		// config default, so gateway selection always receives a string.
		return [
			'country' => $country,
			'currency' => $this->getRequest()->getVal( 'currency' ),
			'payment_method' => $this->getRequest()->getVal( 'payment_method', 'cc' ),
			'payment_submethod' => $this->getRequest()->getVal( 'payment_submethod' ),
			'recurring' => $recurring,
			'gateway' => $this->getRequest()->getVal( 'gateway' ),
			'variant' => $this->getRequest()->getVal( 'variant' ),
		];
	}

	private function chooseGateway( array $params ): ?string {
		$supportedGateways = GatewayRouter::getSupportedGateways(
			$params['country'],
			$params['currency'],
			$params['payment_method'],
			$params['payment_submethod'],
			(bool)$params['recurring'],
			$params['variant'],
			$this->getConfig()
		);

		if ( count( $supportedGateways ) === 0 ) {
			$this->logger->error( 'No supported gateway for parameters: ' . print_r( $params, true ) );

			return null;
		}

		if ( $params['gateway'] && in_array( $params['gateway'], $supportedGateways, true ) ) {
			return $params['gateway'];
		}

		if ( count( $supportedGateways ) === 1 ) {
			return $supportedGateways[0];
		}

		return GatewayRouter::chooseGatewayByPriority(
			$supportedGateways,
			$params,
			$this->getConfig(),
			$this->logger
		);
	}

	/**
	 * Start up a new Gravy Payments session and share the ID, along with the gravy
	 * config, with the frontend.
	 *
	 * @param array &$vars
	 * @param array $params
	 *
	 * @return void
	 */
	protected function addGravyClientConfig( array &$vars, array $params ): void {
		DonationInterface::setSmashPigProvider( 'gravy' );

		$adapter = new GravyAdapter( [
			'external_data' => [
				'payment_method' => $params['payment_method'],
				'currency' => $params['currency'],
				'country' => $params['country'],
				'recurring' => $params['recurring'],
				'language' => $this->getLanguage()->getCode()
			]
		] );

		$vars['gravyConfiguration'] = $adapter->getGravyConfiguration();
		$vars['wmf_token'] = $adapter->token_getSaltedSessionToken();
		$vars['DonationInterfaceThankYouPage'] = ResultPages::getThankYouPage( $adapter );
	}
}
