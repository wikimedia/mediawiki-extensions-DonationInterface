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

class AmazonGateway extends GatewayPage {
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
	 */
	protected function handleRequest() {
		$this->getOutput()->allowClickjacking();

		$this->setHeaders();

		if ( $this->validateForm() ) {
			$this->displayForm();
		} else {
			if ( $this->getRequest()->getText( 'redirect', 0 ) ) {
				if ( $this->getRequest()->getText( 'ffname', 'default' ) === 'amazon-recurring'
					||  $this->getRequest()->getText( 'recurring', 0 )
				) {
					// FIXME: do this in the form param harvesting step
					$this->adapter->addRequestData( array(
						'recurring' => 1,
					) );
				}
				$this->adapter->doPayment();
				// TODO: move redirect here.
				return;
			}

			// TODO: move resultswitching out
			$this->logger->info( 'At gateway return with params: ' . json_encode( $this->getRequest()->getValues() ) );
			if ( $this->adapter->checkTokens() && $this->getRequest()->getText( 'status' ) ) {
				$this->adapter->do_transaction( 'ProcessAmazonReturn' );

				$status = $this->adapter->getFinalStatus();

				// FIXME: Isn't this why we have $goToThankYouOn?
				if ( $status === FinalStatus::COMPLETE || $status === FinalStatus::PENDING ) {
					$this->getOutput()->redirect( $this->adapter->getThankYouPage() );
				}
				else {
					$this->getOutput()->redirect( $this->adapter->getFailPage() );
				}
			} else {
				$specialform = $this->getRequest()->getText( 'ffname', null );
				if ( !is_null( $specialform ) && $this->adapter->isValidSpecialForm( $specialform ) ){
					$this->displayForm();
				} else {
					$this->logger->error( 'Failed to process gateway return. Tokens bad or no status.' );
				}
			}
		}
	}
}
