<?php

class ResultPages {
	/**
	 * Get the URL for a page to show donors after a successful donation,
	 * with the country code appended as a query string variable
	 * @param GatewayType $adapter
	 * @param array $extraParams any extra parameters to add to the URL
	 * @return string full URL of the thank you page
	 */
	public static function getThankYouPage( GatewayType $adapter, $extraParams = [] ) {
		$page = $adapter::getGlobal( "ThankYouPage" );
		if ( $page ) {
			$page = self::appendLanguageAndMakeURL(
				$page,
				$adapter->getData_Unstaged_Escaped( 'language' )
			);
		}
		$extraParams['country'] = $adapter->getData_Unstaged_Escaped( 'country' );
		$extraParams['amount'] = $adapter->getData_Unstaged_Escaped( 'amount' );
		$extraParams['currency'] = $adapter->getData_Unstaged_Escaped( 'currency' );
		$extraParams['payment_method'] = $adapter->getData_Unstaged_Escaped( 'payment_method' );
		$extraParams['order_id'] = $adapter->getData_Unstaged_Escaped( 'order_id' );
		$extraParams['recurring'] = $adapter->getData_Unstaged_Escaped( 'recurring' );
		// Map from internal-facing old utm_ prefixes to wmf_ prefixes that hopefully
		// are less likely to be stripped by privacy-preserving browsers.
		$extraParams['wmf_medium'] = $adapter->getData_Unstaged_Escaped( 'utm_medium' );
		$extraParams['wmf_source'] = $adapter->getData_Unstaged_Escaped( 'utm_source' );
		$extraParams['wmf_campaign'] = $adapter->getData_Unstaged_Escaped( 'utm_campaign' );

		return wfAppendQuery( $page, $extraParams );
	}

	/**
	 * Get the URL for a page to show donors who cancel their attempt
	 * @param GatewayType $adapter instance to use for logger and settings
	 * @return string full URL of the cancel page
	 */
	public static function getCancelPage( GatewayType $adapter ) {
		$cancelPage = $adapter->getGlobal( 'CancelPage' );
		if ( !$cancelPage ) {
			return '';
		}
		return self::appendLanguageAndMakeURL(
			$cancelPage,
			$adapter->getData_Unstaged_Escaped( 'language' )
		);
	}

	/**
	 * For pages we intend to redirect to. This function will take either a full
	 * URL or a page title, and turn it into a URL with the appropriate language
	 * appended onto the end.
	 * @param string $url Either a wiki page title, or a URL to an external wiki
	 * page title.
	 * @param string $language
	 * @return string localized full URL
	 */
	public static function appendLanguageAndMakeURL( $url, $language ) {
		// make sure we don't already have the language in there...
		$dirs = explode( '/', $url );
		if ( !is_array( $dirs ) || !in_array( $language, $dirs ) ) {
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
