<?php

use MediaWiki\SpecialPage\UnlistedSpecialPage;

class DonorPortal extends UnlistedSpecialPage {

	use RequestNewChecksumLinkTrait;

	public function __construct() {
		parent::__construct( 'DonorPortal' );
	}

	/**
	 * Render the donor portal page, or a login page if no checksum is provided
	 * @param string|null $subPage
	 * @return void
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->setUpClientSideChecksumRequest( $subPage );
		$out = $this->getOutput();

		// Adding styles-only modules this way causes them to arrive ahead of page rendering
		$out->addModuleStyles( [
			'donationInterface.skinOverrideStyles',
			'ext.donationInterface.emailPreferencesStyles'
		] );

		$out->addModules( [
			'ext.donationInterface.emailPreferences'
		] );
		$this->getOutput()->setPageTitle( $this->msg( 'donorportal-title' ) );
		$formObj = new DonorPortalForm( 'donorPortal', [
			'showLogin' => true
		] );
		$this->getOutput()->addHTML( $formObj->getForm() );
	}

}
