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

class AmazonGateway extends GatewayForm {
	/**
	 * Constructor - set up the new special page
	 */
	public function __construct() {
		$this->adapter = new AmazonAdapter();
		parent::__construct(); //the next layer up will know who we are.
	}

	/**
	 * Show the special page
	 *
	 * @todo
	 * - Finish error handling
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	public function execute( $par ) {
		global $wgExtensionAssetsPath;
		$CSSVersion = $this->adapter->getGlobal( 'CSSVersion' );

		$out = $this->getOutput();

		$out->allowClickjacking();

		$out->addExtensionStyle(
			$wgExtensionAssetsPath . '/DonationInterface/gateway_forms/css/gateway.css?284' .
			$CSSVersion );

		// Hide unneeded interface elements
		$out->addModules( 'donationInterface.skinOverride' );

		// Make the wiki logo not clickable.
		// @fixme can this be moved into the form generators?
		$js = <<<EOT
<script type="text/javascript">
jQuery(document).ready(function() {
	jQuery("div#p-logo a").attr("href","#");
});
</script>
EOT;
		$out->addHeadItem( 'logolinkoverride', $js );

		$this->setHeaders();

		if ( $this->getRequest()->getText( 'redirect', 0 ) ) {
			$this->adapter->do_transaction( 'Donate' );
			return;
		}


		if ( $this->adapter->checkTokens() && $this->getRequest()->getText( 'status' ) ) {
			$this->adapter->do_transaction( 'ProcessAmazonReturn' );

			$status = $this->adapter->getTransactionWMFStatus();

			if ( ( $status == 'complete' ) || ( $status == 'pending' ) ) {
				$out->redirect( $this->adapter->getThankYouPage() );
			}
			else {
				$out->redirect( $this->adapter->getFailPage() );
			}
		}
	}
}
