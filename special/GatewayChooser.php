<?php

use MediaWiki\Config\Config;
use MediaWiki\Extension\DonationInterface\Special\GatewayRouter;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Title\Title;

/**
 * GatewayChooser acts as a gateway-agnostic landing page.
 * When passed a country, currency, and payment method combination, it determines the
 * appropriate gateway based on gateway configurations and priority rules.
 *
 * @author Damilare Adedoyin <dadedoyin@wikimedia.org>
 * @author Elliott Eggleston <eeggleston@wikimedia.org>
 * @author Wenjun Fan <wfan@wikimedia.org>
 * @author Peter Gehres <pgehres@wikimedia.org>
 * @author Jack Gleeson <jgleeson@wikimedia.org>
 * @author Andrew Green <agreen@wikimedia.org>
 * @author Katie Horn <khorn@wikimedia.org>
 * @author Christine Stone <cstone@wikimedia.org>
 * @author Matt Walker <mwalker@wikimedia.org>
 */
class GatewayChooser extends UnlistedSpecialPage {

	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	public function __construct() {
		$this->logger = DonationLoggerFactory::getLoggerForType( 'GatewayAdapter', 'GatewayChooser' );
		parent::__construct( 'GatewayChooser' );
	}

	/** @inheritDoc */
	public function execute( $par ) {
		// Bow out if gateway chooser is not enabled
		if ( !$this->getConfig()->get( 'DonationInterfaceEnableGatewayChooser' ) ) {
			throw new BadTitleError();
		}

		// Also bow out if we're in maintenance mode
		if ( $this->getConfig()->get( 'DonationInterfaceFundraiserMaintenance' ) ) {
			$this->getOutput()->redirect( Title::newFromText( 'Special:FundraiserMaintenance' )->getFullURL(), '302' );
			return;
		}

		// Get an associative array of params from the URL. The ones we look at to determine
		// gateway options will be sanitized/vaidated, and the rest will be fetched verbatim.
		$params = $this->getParamsFromURL();

		if ( $params[ 'country' ] && $params[ 'payment_method' ] ) {
			// Find possible gateways
			$supportedGateways = GatewayRouter::getSupportedGateways(
				$params['country'],
				$params['currency'],
				$params['payment_method'],
				$params['payment_submethod'],
				$params['recurring'],
				$params['variant'],
				$this->getConfig()
			);
		} else {
			// redirect to ways to give
			$this->logger->warning(
				'Missing country or payment_method at GatewayChooser for query string ' .
				$this->getRequest()->getRawQueryString() .
				' and referer ' .
				$this->getRequest()->getHeader( 'referer' )
			);
			$this->getOutput()->redirect( $this->getProblemRedirectUrl() );
			return;
		}

		// If there are no supported gateways for these inputs, log an error and show an
		// error page.
		if ( count( $supportedGateways ) === 0 ) {
			$this->logger->error(
				'No supported gateway for parameters: ' . print_r( $params, true ) .
				' from referrer ' . $this->getRequest()->getHeader( 'referer' ) );

			$this->getOutput()->showErrorPage(
				'donate_interface-error-msg-general',
				'donate_interface-error-no-form',
				[ $this->getConfig()->get( 'DonationInterfaceProblemsEmail' ) ]
			);

			return;
		}

		// If a specific gateway was requested and it's supported, choose it
		if ( $params[ 'gateway' ] && in_array( $params[ 'gateway' ], $supportedGateways ) ) {
			$selectedGateway = $params[ 'gateway' ];

		} elseif ( count( $supportedGateways ) === 1 ) {
			// If only one gateway is supported, choose it
			$selectedGateway = $supportedGateways[ 0 ];

		} else {
			// We need to choose from among two or more supported gateways
			$selectedGateway = GatewayRouter::chooseGatewayByPriority(
				$supportedGateways,
				$params,
				$this->getConfig(),
				$this->logger
			);
		}

		// Get the URL and perform the redirection
		$redirectURL = self::buildGatewayPageURL( $selectedGateway, $params, $this->getConfig() );
		$this->getOutput()->redirect( $redirectURL );
	}

	/**
	 * Build a URL to a payments form with the data that we have.
	 *
	 * @param string $gateway The short name of the payment gateway.
	 * @param array $params An array of params to send to the gateway page.
	 * @param Config $mwConfig MediaWiki Config
	 *
	 * @return string URL of special page for $gateway with $params on the querystring.
	 */
	public static function buildGatewayPageURL( string $gateway, array $params, Config $mwConfig ) {
		// Remove empty strings (normally not expected)
		$params = array_filter( $params, static function ( $v ) {
			return $v !== '';
		} );

		// Add an appeal parameter if none was present
		$params = array_merge( [
			'appeal' => $mwConfig->get( 'DonationInterfaceDefaultAppeal' ),
		], $params );

		$specialPage = GatewayPage::getGatewayPageName( $gateway, $mwConfig );
		return self::getTitleFor( $specialPage )->getLocalURL( $params );
	}

	/**
	 * Get params from the URL, sanitizing and, in some cases, validating the ones we
	 * use to get possible gateway, and including the rest verbatim.
	 *
	 * @return array associative array of params from the URL
	 */
	private function getParamsFromURL(): array {
		// Get country code from request param, or, if it's not sent or is invalid, use
		// geoip lookup
		$country = $this->sanitizedValOrNull( 'country' );

		if ( !CountryValidation::isValidIsoCode( $country ) ) {
			$country = CountryValidation::lookUpCountry( $this->getRequest()->getIP() );

			if ( !$country || !CountryValidation::isValidIsoCode( $country ) ) {
				$this->logger->warning(
					"GeoIP lookup returned invalid country '$country'!, " .
					'from referrer ' . $this->getRequest()->getHeader( 'referer' ) );
			}
		}

		// Get currency from request param. Also check for a value from the currency_code
		// param, and if there is one, warn so we can track and remove these
		$currency = $this->sanitizedValOrNull( 'currency' );

		$paymentMethod = $this->sanitizedValOrNull( 'payment_method' );
		// No payment method will cause an error a little further down
		if ( !$paymentMethod ) {
			$this->logger->warning(
				'No payment method URL param from referrer ' .
				$this->getRequest()->getHeader( 'referer' ) );
		}

		// For recurring, we'll interpret no URL param, 'false', '0' and '' as false.
		// This follows legacy behavior, to ensure existing links work as expected.

		// sanitizedValOrNull() will return null if the param is absent or ''
		$recurringRawVal = $this->sanitizedValOrNull( 'recurring' );

		// We map this to 0 or 1 rather than boolean true or false because the
		// getLocalURL function we eventually feed this to will discard any param
		// whose value is false.
		if ( $recurringRawVal === 'false' ) {
			$recurring = 0;
		} elseif ( $recurringRawVal === 'true' ) {
			$recurring = 1;
		} else {
			// map null to 0, as we want to affirmatively overwrite any recurring=1
			// from a previous attempt if there is no recurring value on this URL.
			$recurring = (int)$recurringRawVal;
		}

		// These are the parameters that are actually used to find possible gateways
		$params = [
			'country' => $country,
			'currency' => $currency,
			'payment_method' => $paymentMethod,
			'payment_submethod' => $this->sanitizedValOrNull( 'payment_submethod' ),
			'recurring' => $recurring,
			'gateway' => $this->sanitizedValOrNull( 'gateway' ),
			'variant' => $this->sanitizedValOrNull( 'variant' )
		];

		// All other URL parameters (except title) will be passed through on
		// the redirect URL without sanitization or validation
		$passThruParams = [];
		foreach ( $this->getRequest()->getValues() as $key => $value ) {
			if ( !array_key_exists( $key, $params ) && $key !== 'title' ) {
				$passThruParams[ $key ] = $value;
			}
		}

		return $params + $passThruParams;
	}

	/**
	 * Get the sanitized string value of a URL parameter. If the parameter was not present
	 * or is an empty string, return null.
	 *
	 * @param string $paramName
	 * @return ?string
	 */
	private function sanitizedValOrNull( string $paramName ): ?string {
		$val = $this->getRequest()->getVal( $paramName, null );

		if ( $val === '' || $val === null ) {
			return null;
		}

		$sanitizedVal = preg_replace( "/[^A-Za-z0-9_\-]+/", "", $val );

		if ( $sanitizedVal !== $val ) {
			$this->logger->warning(
				"Unexpected characters in $paramName; sanitized value is $sanitizedVal, " .
				'from referrer ' . $this->getRequest()->getHeader( 'referer' )
			);
		}

		return $sanitizedVal;
	}

	protected function getProblemRedirectUrl(): string {
		$problemsUrl = $this->getConfig()->get( 'DonationInterfaceNewDonationURL' );
		$queryValues = $this->getRequest()->getQueryValues() ?? [];
		unset( $queryValues['title'] );
		if ( count( $queryValues ) > 0 ) {
			$glue = strpos( $problemsUrl, '?' ) === false ? '?' : '&';
			$problemsUrl .= $glue . http_build_query( $queryValues );
		}
		return $problemsUrl;
	}
}
