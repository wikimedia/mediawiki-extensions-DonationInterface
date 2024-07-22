<?php

class PaymentSettings extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'PaymentSettings' );
	}

	public function execute( $par ) {
		$this->getOutput()->setPageTitle( 'Payment Settings' );

		// this config comes from the config in extension.json
		//$config = $this->getConfig()->get( 'DonationInterfaceGatewayPriorityRules' );
		$processors = [ 'adyen', 'dlocal' ];

		foreach ( $processors as $processor ) {
			$this->getOutput()->addHTML( '<h1>' . $processor . '</h1>' );
			$configurationReader = ConfigurationReader::createForGateway(
				$processor, null, WmfFramework::getConfig()
			);
			$config = $configurationReader->readConfiguration();
			$this->getOutput()->addHTML( 'Enabled payment methods by country' );
			foreach ( $config['payment_submethods'] as $name => $submethod ) {
				$this->getOutput()->addHTML( '<h2>' . $name . '</h2>' );
				$this->getOutput()->addHTML( '<table>' );
				if ( isset( $submethod['countries'] ) ) {
					foreach ( $submethod['countries'] as $countrycode => $enabled ) {
						$this->getOutput()->addHTML( '<tr>' );
						$this->getOutput()->addHTML( '<td>' . $countrycode . '</td>' );
						// can we easily display the full name
						$fullname = Locale::getDisplayRegion( '-' . $countrycode, 'en' );
						$this->getOutput()->addHTML( '<td>' . $fullname . '</td>' );
						$this->getOutput()->addHTML( '</tr>' );
					}
				}
				$this->getOutput()->addHTML( '</table>' );
			}
		}
	}
}
