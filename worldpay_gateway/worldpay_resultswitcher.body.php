<?php

class WorldpayGatewayResult extends GatewayPage {

	public function __construct() {
		$this->adapter = new WorldpayAdapter();
		parent::__construct();
	}

	protected function handleRequest() {
		// Break out of the iframe, signal to skip this next time, and reload.
		if ( ! $this->getRequest()->getText('liberated') ) {
			$this->adapter->addRequestData( array( 'liberated', '1' ) );
			$this->getOutput()->allowClickjacking();
			$this->getOutput()->addModules( 'iframe.liberator' );
			return;
		}

		// After the iframe breakout, load up the WP token.
		$this->adapter->addRequestData(
			array ( 'wp_one_time_token' => $this->getRequest()->getText( 'OTT' ) )
		);

		// And process the donation.
		if ( $this->adapter->checkTokens() ) {
			$result = $this->adapter->do_transaction( 'QueryAuthorizeDeposit' ); // TODO handle errors here
			switch ( $this->adapter->getFinalStatus() ) {
				case FinalStatus::COMPLETE:
				case FinalStatus::PENDING:
				case FinalStatus::PENDING_POKE:
					$this->getOutput()->redirect( $this->adapter->getThankYouPage() );
					break;
				case FinalStatus::FAILED:
					$this->displayFailPage();
					return;
			}
		} else {
			$error['general']['token-mismatch'] = $this->msg( 'donate_interface-token-mismatch' );
			$this->adapter->addManualError( $error );
			$this->displayForm();
		}
	}
}
