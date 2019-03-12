<?php

use Psr\Log\LoggerInterface;

class ResultPages {
	/**
	 * Get the URL for a page to show donors after a successful donation,
	 * with the country code appended as a query string variable
	 * @param GatewayType $adapter
	 * @param array $extraParams any extra parameters to add to the URL
	 * @return string full URL of the thank you page
	 */
	public static function getThankYouPage( GatewayType $adapter, $extraParams = array() ) {
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
	 * Get the URL for a page to show donors after a failed donation
	 * @param GatewayType $adapter instance to use for logger and settings
	 * @return string full URL of the fail page, or just form name in case of rapidFail
	 */
	public static function getFailPage( GatewayType $adapter ) {
		return self::getFailPageFromParams(
			$adapter->getGlobal( 'RapidFail' ),
			$adapter->getGlobal( 'FailPage' ),
			$adapter->getData_Unstaged_Escaped(),
			DonationLoggerFactory::getLogger( $adapter )
		);
	}

	/**
	 * Get the URL for a page to show donors after a failed donation without
	 * requiring an adapter instance.
	 * @param string $adapterType adapter class to use for settings and logger
	 *                            e.g. AdyenGateway
	 * @param string $logPrefix identifier used to associate log lines with
	 *                          related requests
	 * @return string full URL of the fail page
	 */
	public static function getFailPageForType( $adapterType, $logPrefix = '' ) {
		return self::getFailPageFromParams(
			false, // Can't render RapidFail form without an instance
			$adapterType::getGlobal( 'FailPage' ),
			array(
				'gateway' => $adapterType::getIdentifier(),
				'payment_method' => '',
				'payment_submethod' => '',
			),
			DonationLoggerFactory::getLoggerForType( $adapterType, $logPrefix )
		);
	}

	/**
	 * @param bool $rapidFail if true, render a form as a fail page rather than redirect
	 * @param string $failPage either a wiki page title, or a URL to an external wiki
	 *                         page title.
	 * @param array $data information about the current request.
	 *                    language, gateway, payment_method, and payment_submethod must be set
	 * @param Psr\Log\LoggerInterface $logger
	 * @return string full URL of the fail page, or just form name in case of rapidFail
	 */
	private static function getFailPageFromParams( $rapidFail, $failPage, $data, LoggerInterface $logger ) {
		if ( isset( $data['language'] ) ) {
			$language = $data['language'];
		} else {
			$language = WmfFramework::getLanguageCode();
		}
		// Prefer RapidFail.
		if ( $rapidFail ) {
			// choose which fail page to go for.
			try {
				$fail_ffname = GatewayFormChooser::getBestErrorForm(
					$data['gateway'],
					$data['payment_method'],
					$data['payment_submethod']
				);
				return $fail_ffname;
			} catch ( Exception $e ) {
				$logger->error( 'Cannot determine best error form. ' . $e->getMessage() );
			}
		}

		if ( filter_var( $failPage, FILTER_VALIDATE_URL ) ) {
			return self::appendLanguageAndMakeURL( $failPage, $language );
		}
		// FIXME: either add Special:FailPage to avoid depending on wiki content,
		// or update the content on payments to be consistent with the /lang
		// format of ThankYou pages so we can use appendLanguageAndMakeURL here.
		$failTitle = Title::newFromText( $failPage );
		$url = wfAppendQuery( $failTitle->getFullURL(), array( 'uselang' => $language ) );

		return $url;
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
