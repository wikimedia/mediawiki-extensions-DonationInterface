<?php

class PaymentSettings extends UnlistedSpecialPage {
	public const PAYMENT_PROCESSORS = [
		'adyen',
		'gravy',
		'dlocal',
	];

	public function __construct() {
		parent::__construct( 'PaymentSettings' );
	}

	/**
	 * @param null|string $subPage
	 *
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->getOutput()->setPageTitle( 'Payment Processor Settings' );
		$this->displayTableOfContents();
		$this->displayMonthlyConvertCountries();
		foreach ( self::PAYMENT_PROCESSORS as $processor ) {
			$this->displayPaymentProcessorSection( $processor );
		}
	}

	/**
	 * Generate and display the Table of Contents (TOC).
	 *
	 * @return void
	 */
	private function displayTableOfContents(): void {
		$tocHTML = '<div id="toc" class="mw-collapsible mw-made-collapsible mw-collapsible-open">';
		$tocHTML .= '<h3>Table of Contents</h3>';
		$tocHTML .= '<ul>';
		$tocHTML .= '<li><a href="#monthly-convert-countries">Monthly Convert Countries</a></li>';
		foreach ( self::PAYMENT_PROCESSORS as $processor ) {
			$tocHTML .= '<li><a href="#' . $processor . '">' . ucfirst( $processor ) . '</a></li>';
		}
		$tocHTML .= '</ul>';
		$tocHTML .= '</div>';

		$this->getOutput()->addHTML( $tocHTML );
	}

	/**
	 * Display Monthly Convert Countries.
	 *
	 * @return void
	 */
	private function displayMonthlyConvertCountries(): void {
		$this->getOutput()->addHTML( '<h2 id="monthly-convert-countries">Monthly Convert Countries</h2>' );
		$this->getOutput()->addHTML( '<table class="wikitable">' );
		$this->getOutput()->addHTML( '
            <thead>
                <tr>
                    <th style="text-align: left;padding-right: 50px;">Country Code</th>
                    <th>Country Name</th>
                </tr>
            </thead>
            <tbody>
        ' );

		$monthlyConvert = $this->getConfig()->get( 'DonationInterfaceMonthlyConvertCountries' );
		foreach ( $monthlyConvert as $countryCode ) {
			$countryName = Locale::getDisplayRegion( '-' . $countryCode,
				'en' );
			$this->getOutput()->addHTML( "
                <tr>
                    <td>{$countryCode}</td>
                    <td>{$countryName}</td>
                </tr>
            " );
		}

		$this->getOutput()->addHTML( '</tbody></table>' );
	}

	/**
	 * Display a section for each Payment Processor.
	 *
	 * @param string $paymentProcessor
	 *
	 * @return void
	 */
	private function displayPaymentProcessorSection( string $paymentProcessor ): void {
		$this->getOutput()->addHTML( '<h1 id="' . $paymentProcessor . '">' . ucfirst( $paymentProcessor ) . '</h1>' );

		$configurationReader = ConfigurationReader::createForGateway(
			$paymentProcessor,
			null,
			WmfFramework::getConfig()
		);
		$paymentProcessorConfig = $configurationReader->readConfiguration();
		$this->displayPaymentMethods( $paymentProcessorConfig['payment_methods'] );
		$this->displayPaymentSubmethods( $paymentProcessorConfig['payment_submethods'] );
	}

	/**
	 * Display Payment Methods table.
	 *
	 * @param array $paymentMethods
	 *
	 * @return void
	 */
	private function displayPaymentMethods( array $paymentMethods ): void {
		$this->getOutput()->addHTML( '<h3>Payment Methods</h3>' );
		$this->getOutput()->addHTML( '<table class="wikitable">' );
		$this->getOutput()->addHTML( '
            <thead>
                <tr>
                    <th style="text-align: left;padding-right: 50px;">Name</th>
                    <th style="padding-right: 20px;">Identifier</th>
                    <th style="padding-right: 20px;">Default</th>
                    <th>Recurring</th>
                </tr>
            </thead>
            <tbody>
        ' );

		foreach ( $paymentMethods as $name => $method ) {
			$default = !empty( $method['is_default'] ) ? 'Yes' : 'No';
			$recurring = !empty( $method['recurring'] ) ? 'Yes' : 'No';
			$label = $method['label'] ?? "";
			$this->getOutput()->addHTML( "
                <tr>
                    <td style=\"padding-right: 50px;\">{$label}</td>
                    <td>{$name}</td>
                    <td>{$default}</td>
                    <td>{$recurring}</td>
                </tr>
            " );
		}

		$this->getOutput()->addHTML( '</tbody></table>' );
	}

	/**
	 * Display Payment Submethods table.
	 *
	 * @param array $paymentSubmethods
	 *
	 * @return void
	 */
	private function displayPaymentSubmethods( array $paymentSubmethods ): void {
		$this->getOutput()->addHTML( '<h3>Payment Submethods by Country</h3>' );
		foreach ( $paymentSubmethods as $name => $submethod ) {
			$this->getOutput()->addHTML( '<h2>' . $name . '</h2>' );
			$this->getOutput()->addHTML( '<table class="wikitable">' );
			$this->getOutput()->addHTML( '
                <thead>
                    <tr>
                        <th style="text-align: left;padding-right: 50px;">Country Code</th>
                        <th>Country Name</th>
                    </tr>
                </thead>
                <tbody>
            ' );

			if ( isset( $submethod['countries'] ) ) {
				foreach ( $submethod['countries'] as $countryCode => $enabled ) {
					$countryName = Locale::getDisplayRegion( '-' . $countryCode,
						'en' );
					$this->getOutput()->addHTML( "
                        <tr>
                            <td>{$countryCode}</td>
                            <td>{$countryName}</td>
                        </tr>
                    " );
				}
			}

			$this->getOutput()->addHTML( '</tbody></table>' );
		}
	}
}
