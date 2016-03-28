<?php

class WorldpayGatewayResult extends GatewayPage {

	protected $adapterClass = 'WorldpayAdapter';

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
			$status = $this->adapter->getFinalStatus();
			switch ( $status ) {
				case FinalStatus::COMPLETE:
				case FinalStatus::PENDING:
				case FinalStatus::PENDING_POKE:
					$this->logger->info( "Displaying thank you page for status $status." );
					$this->getOutput()->redirect( ResultPages::getThankYouPage( $this->adapter ) );
					break;
				case FinalStatus::FAILED:
					$this->logger->info( 'Displaying fail page for final status failed.' );
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
