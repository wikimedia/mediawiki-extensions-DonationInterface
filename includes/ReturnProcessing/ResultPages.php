<?php

namespace MediaWiki\Extension\DonationInterface\ReturnProcessing;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Title\Title;

class ResultPages {

	/**
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'DonationInterfaceThankYouPage',
		'DonationInterfaceCancelPage',
	];

	public const THANK_YOU_PASSTHROUGH_PARAMS = [
		'amount',
		'country',
		'currency',
		'frequency_unit',
		'order_id',
		'payment_method',
		'recurring',
	];

	public const THANK_YOU_MAPPED_PARAMS = [
		'utm_medium' => 'wmf_medium',
		'utm_source' => 'wmf_source',
		'utm_campaign' => 'wmf_campaign',
	];

	public function __construct( private readonly ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Get the URL for a page to show donors after a successful donation,
	 * with information about the donation appended as query string variables
	 * @param \GatewayType $adapter
	 * @param array $extraParams any extra parameters to add to the URL
	 * @return string full URL of the thank you page
	 */
	public function getThankYouPage( \GatewayType $adapter, $extraParams = [] ): string {
		return $this->getThankYouPageForParams(
			$adapter->getData_Unstaged_Escaped(),
			$extraParams
		);
	}

	/**
	 * Get the URL for a page to show donors after a successful donation,
	 * with information about the donation appended as query string variables
	 * @param array $donationParams standard donation parameters
	 * @param array $extraParams any extra parameters to add to the URL
	 * @return string full URL of the thank you page
	 */
	public function getThankYouPageForParams( array $donationParams, array $extraParams = [] ): string {
		$page = self::getBaseThankYouPage( $donationParams['appeal'] ?? null );
		if ( $page ) {
			$page = self::appendLanguageAndMakeURL(
				$page,
				$donationParams['language'] ?? null
			);
		}
		$thankYouParams = $extraParams;

		foreach ( self::THANK_YOU_PASSTHROUGH_PARAMS as $key ) {
			$thankYouParams[$key] = $donationParams[$key] ?? null;
		}

		// Map from internal-facing old utm_ prefixes to wmf_ prefixes that hopefully
		// are less likely to be stripped by privacy-preserving browsers.
		foreach ( self::THANK_YOU_MAPPED_PARAMS as $key => $mappedKey ) {
			$thankYouParams[$mappedKey] = $donationParams[$key] ?? null;
		}

		return wfAppendQuery( $page, $thankYouParams );
	}

	/**
	 * Get the base thank-you page, potentially indexed by the current 'appeal' value
	 * @param string|null $appeal
	 * @return string
	 */
	public function getBaseThankYouPage( ?string $appeal = '' ): string {
		$page = $this->options->get( 'DonationInterfaceThankYouPage' );
		if ( is_array( $page ) ) {
			if ( array_key_exists( $appeal, $page ) ) {
				$page = $page[$appeal];
			} elseif ( array_key_exists( 'default', $page ) ) {
				$page = $page['default'];
			} else {
				$page = array_values( $page )[0];
			}
		}
		return $page;
	}

	/**
	 * Get the URL for a page to show donors who cancel their attempt
	 * @param string|null $language language to use for URL
	 * @return string full URL of the cancel page
	 */
	public function getCancelPage( ?string $language ): string {
		$cancelPage = $this->options->get( 'DonationInterfaceCancelPage' );
		if ( !$cancelPage ) {
			return '';
		}
		return self::appendLanguageAndMakeURL(
			$cancelPage,
			$language
		);
	}

	/**
	 * For pages we intend to redirect to. This function will take either a full
	 * URL or a page title, and turn it into a URL with the appropriate language
	 * appended onto the end.
	 * @param string $url Either a wiki page title, or a URL to an external wiki
	 * page title.
	 * @param string|null $language
	 * @return string localized full URL
	 */
	public static function appendLanguageAndMakeURL( string $url, ?string $language ): string {
		// make sure we don't already have the language in there...
		$dirs = explode( '/', $url );
		if ( !in_array( $language, $dirs ) ) {
			$url .= "/$language";
		}

		if ( str_starts_with( $url, 'http' ) ) {
			return $url;
		} else { // this isn't a url yet.
			$returnTitle = Title::newFromText( $url );
			$url = $returnTitle->getFullURL( [], false, PROTO_CURRENT );
			return $url;
		}
	}
}
