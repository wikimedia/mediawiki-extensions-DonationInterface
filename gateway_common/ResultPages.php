<?php

class ResultPages {
	/**
	 * Get the URL for a page to show donors after a successful donation,
	 * with the country code appended as a query string variable
	 * @param GatewayType $adapter
	 * @param array $extraParams any extra parameters to add to the URL
	 * @return string
	 */
	public static function getThankYouPage( GatewayType $adapter, $extraParams = array() ) {
		$page = $adapter::getGlobal( "ThankYouPage" );
		if ( $page ) {
			$page = self::appendLanguageAndMakeURL( $page, $adapter );
		}
		$extraParams['country'] = $adapter->getData_Unstaged_Escaped( 'country' );
		return wfAppendQuery( $page, $extraParams );
	}

	/**
	 * Get the URL for a page to show donors after a failed donation
	 * @param GatewayType $adapter
	 * @return string
	 */
	public static function getFailPage( GatewayType $adapter ) {
		// Prefer RapidFail.
		if ( $adapter::getGlobal( 'RapidFail' ) ) {
			$data = $adapter->getData_Unstaged_Escaped();

			// choose which fail page to go for.
			try {
				$fail_ffname = GatewayFormChooser::getBestErrorForm( $data['gateway'], $data['payment_method'], $data['payment_submethod'] );
				return GatewayFormChooser::buildPaymentsFormURL( $fail_ffname, $adapter->getRetryData() );
			} catch ( Exception $e ) {
				$logger = DonationLoggerFactory::getLogger( $adapter );
				$logger->error( 'Cannot determine best error form. ' . $e->getMessage() );
			}
		}
		$page = $adapter::getGlobal( 'FailPage' );
		if ( filter_var( $page, FILTER_VALIDATE_URL ) ) {
			return self::appendLanguageAndMakeURL( $page, $adapter );
		}

		// FIXME: either add Special:FailPage to avoid depending on wiki content,
		// or update the content on payments to be consistent with the /lang
		// format of ThankYou pages so we can use appendLanguageAndMakeURL here.
		$failTitle = Title::newFromText( $page );
		$language = $adapter->getData_Unstaged_Escaped( 'language' );
		$url = wfAppendQuery( $failTitle->getFullURL(), array( 'uselang' => $language ) );

		return $url;
	}

	/**
	 * Get the URL for a page to show donors who cancel their attempt
	 * @param GatewayType $adapter
	 * @return string
	 */
	public static function getCancelPage( GatewayType $adapter ) {
		$cancelPage = $adapter->getGlobal( 'CancelPage' );
		if ( empty( $cancelPage ) ) {
			return '';
		}
		return self::appendLanguageAndMakeURL( $cancelPage, $adapter );
	}

	/**
	 * For pages we intend to redirect to. This function will take either a full
	 * URL or a page title, and turn it into a URL with the appropriate language
	 * appended onto the end.
	 * @param string $url Either a wiki page title, or a URL to an external wiki
	 * page title.
	 * @param GatewayType $adapter
	 * @return string A URL
	 */
	protected static function appendLanguageAndMakeURL( $url, GatewayType $adapter ) {
		$language = $adapter->getData_Unstaged_Escaped( 'language' );
		// make sure we don't already have the language in there...
		$dirs = explode('/', $url);
		if ( !is_array( $dirs ) || !in_array( $language, $dirs ) ) {
			$url = $url . "/$language";
		}

		if ( strpos( $url, 'http' ) === 0 ) {
			return $url;
		} else { // this isn't a url yet.
			$returnTitle = Title::newFromText( $url );
			$url = $returnTitle->getFullURL();
			return $url;
		}
	}
}
