<?php

/**
 * AdyenCheckoutGateway
 *
 * Special page that uses the Adyen Checkout web implementation to accept donations
 */
class AdyenCheckoutGateway extends GatewayPage {

	protected $gatewayIdentifier = AdyenCheckoutAdapter::IDENTIFIER;

	public function execute( $par ) {
		parent::execute( $par );
		$out = $this->getOutput();
		$script = $this->adapter->getAccountConfig( 'Script' );
		$css = $this->adapter->getAccountConfig( 'Css' );
		$out->addScript(
			"<script src=\"{$script['src']}\" " .
			"integrity=\"{$script['integrity']}\" " .
			'crossorigin="anonymous"></script>'
		);
		$out->addLink(
			[
				'rel' => 'stylesheet',
				'href' => $css['src'],
				'integrity' => $css['integrity'],
				'crossorigin' => 'anonymous'
			]
		);
	}
}
