<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\UnlistedSpecialPage;

/**
 * ComboWiki: the single-page VueJS donation flow.
 *
 * Skeleton special page that boots the ComboWiki Vue application. Modelled on
 * DonorPortal: it loads the Vue + styles ResourceLoader modules, sets up the
 * viewport, and exposes server-side configuration to the client through the
 * MakeGlobalVariablesScript hook.
 */
class ComboWiki extends UnlistedSpecialPage {

	public function __construct() {
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
		// TODO: populate with the data the form needs (amounts, currency,
		// country, gateway config, tracking params, etc.) as the build grows.
		$vars['comboWiki'] = [
			'language' => $this->getLanguage()->getCode(),
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
}
