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
		return wfAppendQuery( $page, $extraParams );
	}

	/**
	 * Get the URL for a page to show donors who cancel their attempt
	 * @param GatewayType $adapter instance to use for logger and settings
	 * @return string full URL of the cancel page
	 */
	public static function getCancelPage( GatewayType $adapter ) {
		$cancelPage = $adapter->getGlobal( 'CancelPage' );
		if ( empty( $cancelPage ) ) {
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
	protected static function appendLanguageAndMakeURL( $url, $language ) {
		// make sure we don't already have the language in there...
		$dirs = explode( '/', $url );
		if ( !is_array( $dirs ) || !in_array( $language, $dirs ) ) {
			$url = $url . "/$language";
		}

		if ( strpos( $url, 'http' ) === 0 ) {
			return $url;
		} else { // this isn't a url yet.
			$returnTitle = Title::newFromText( $url );
			$url = $returnTitle->getFullURL( false, false, PROTO_CURRENT );
			return $url;
		}
	}
}
