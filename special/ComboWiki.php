<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DonationInterface\Special\GatewayRouter;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use Psr\Log\LoggerInterface;

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
	 * @return void
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->setPageTitleMsg( $this->msg( 'combowiki-title' ) );

		// Expose server-side config to the Vue app.
		$this->getHookContainer()->register(
			'MakeGlobalVariablesScript', [ $this, 'setClientVariables' ]
		);

		$this->addStylesScriptsAndViewport();
	}

	/**
	 * Set variables to be read in client-side JS code.
	 * @param array &$vars
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
				'meta', [
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

	private function getRoutingParams(): array {
		$country = $this->getRequest()->getVal( 'country' );
		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			$country = CountryValidation::lookUpCountry( $this->getRequest()->getIP() );
		}

		$recurringRawValue = $this->getRequest()->getVal( 'recurring' );
		if ( $recurringRawValue === 'false' ) {
			$recurring = 0;
		} elseif ( $recurringRawValue === 'true' ) {
			$recurring = 1;
		} else {
			$recurring = (int)$recurringRawValue;
		}

		return [
			'country' => $country,
			'currency' => $this->getRequest()->getVal( 'currency' ),
			'payment_method' => $this->getRequest()->getVal( 'payment_method' ),
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
			$supportedGateways, $params, $this->getConfig(), $this->logger
		);
	}
}
