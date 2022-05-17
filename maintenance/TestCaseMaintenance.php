#!/usr/bin/env php
<?php

use SmashPig\PaymentData\ReferenceData\NationalCurrencies;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class TestCaseMaintenance extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'Donation Interface' );
	}

	public function execute() {
		global $wgDonationInterfaceAllowedHtmlForms;

		$cases = [];
		$combinations = [];

		foreach ( $wgDonationInterfaceAllowedHtmlForms as $name => $form ) {
			if ( isset( $form['payment_methods'] ) ) {
				foreach ( $form['payment_methods'] as $ffname => $subName ) {
					if ( isset( $form['countries'] ) ) {
						if ( isset( $form['countries']['+'] ) && is_array( $form['countries']['+'] ) ) {
							foreach ( $form['countries']['+'] as $country ) {
								// find the correspondent currency, then combine the
								if ( $country !== null ) {
									$combination = $ffname . '_' . $country;
									if ( !$combinations[$combination] ) {
										$combinations[$combination] = true;
									}
									$defaultCurrency = NationalCurrencies::getNationalCurrency( $country );
									if ( $defaultCurrency !== null ) {
										$params = [ 'payment_method' => $ffname, 'country' => $country, 'currency' => $defaultCurrency ];
										$gateway = $this->getGateway( $params );
										if ( $gateway !== '' ) {
											$cases[] = [ $params, $this->getGateway( $params ) ];
										}
									}
								}
							}
						}
					}
				}
			}
		}
		echo count( $cases );
		$json = json_encode( $cases );
		file_put_contents( "testCases.json", $json );
		// then convert the json file as data provider, to do the form chooser test: php tests/phpunit/phpunit.php --filter testAssertExpectedGateway
	}

	protected function getGateway( $params ) {
		$context = RequestContext::getMain();
		$newOutput = new OutputPage( $context );
		$newTitle = Title::newFromText( 'nonsense is apparently fine' );
		$context->setRequest( new FauxRequest( $params, false ) );
		$context->setOutput( $newOutput );
		$context->setTitle( $newTitle );
		$fc = new GatewayChooser();
		$fc->execute( $params );
		$fc->getOutput()->output();
		$url = $fc->getRequest()->response()->getheader( 'Location' );
		$parts = parse_url( $url );
		parse_str( $parts['query'], $query );
		$gateway = str_replace( 'Special:', '', $query['title'] );
		return $gateway;
	}
}

$mainClass = TestCaseMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
