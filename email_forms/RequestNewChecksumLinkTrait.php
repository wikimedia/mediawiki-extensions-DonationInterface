<?php

use MediaWiki\Extension\DonationInterface\RecurUpgrade\Validator;

trait RequestNewChecksumLinkTrait {

	protected ?string $subpage;

	protected function setUpClientSideChecksumRequest( $subpage ) {
		// @phan-suppress-next-line PhanUndeclaredMethod
		$this->getOutput()->addModules( 'ext.donationInterface.requestNewChecksumLink' );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$this->getHookContainer()->register(
			'MakeGlobalVariablesScript', [ $this, 'setClientVariables' ]
		);
		$this->subpage = $subpage;
	}

	public function setClientVariables( &$vars ) {
		$vars['showRequestNewChecksumModal'] = $this->isChecksumExpired();
		// @phan-suppress-next-line PhanUndeclaredMethod
		$vars['requestNewChecksumPage'] = $this->getPageTitle()->getBaseText();
		$vars['requestNewChecksumSubpage'] = $this->subpage;
	}

	protected function isChecksumExpired() {
		return Validator::isChecksumExpired(
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->getRequest()->getVal( 'checksum' )
		);
	}
}
