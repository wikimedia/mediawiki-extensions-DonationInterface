<?php

/**
 * Gateway-agnostic result page for the ComboWiki donation flow.
 *
 * ComboWiki routes donations across payment processors (see GatewayRouter), so
 * unlike the per-gateway result pages it does not hard-code a gateway. It
 * recovers the processor that handled this donation at request time and reuses
 * ResultSwitcher's return handling (session + token checks, processDonorReturn,
 * thank-you redirect) unchanged.
 *
 * Heads up: this class is deliberately NOT namespaced (there's no
 * "namespace ..." line), even though the ComboWiki special page is.
 *
 * The parent class works out this page's name automatically from the class
 * name. If we namespaced it, the page name would come out as the long
 * "MediaWiki\Extension\DonationInterface\Special\ComboWikiGatewayResult"
 * instead of just "ComboWikiGatewayResult", which breaks the URL the donor
 * returns to after paying. Keeping the class un-namespaced keeps the page name
 * short and correct. Every other gateway result page is un-namespaced too, for
 * the same reason.
 */
class ComboWikiGatewayResult extends ResultSwitcher {

	/**
	 * @return string
	 */
	protected function getGatewayIdentifier(): string {
		// the gateway is decided server-side by the priority rules.
		// Here, we recover it from the backend 'gateway' param
		// falling back to the session data if needed.
		$gateway = $this->getRequest()->getVal( 'gateway' );
		if ( $gateway ) {
			return $gateway;
		}
		$donorData = WmfFramework::getSessionValue( 'Donor' );
		return $donorData['gateway'] ?? '';
	}
}
