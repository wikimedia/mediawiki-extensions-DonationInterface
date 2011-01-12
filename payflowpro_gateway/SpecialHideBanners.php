<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'DonationInterface' );
}

/**
 * Unlisted Special page to set cookies for hiding banners across all wikis.
 *
 * @ingroup Extensions
 */
class SpecialHideBanners extends UnlistedSpecialPage {
	function __construct() {
		parent::__construct( 'HideBanners' );
	}

	function execute( $par ) {
		global $wgRequest, $wgOut;

		$this->setGlobalCookies();

		$wgOut->disable();

		wfResetOutputBuffers();
		//header( "Content-type: text/html; charset=utf-8" );
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: no-cache' );
		
		readfile( dirname( __FILE__ ) . '/includes/1x1.png' );
	}
	
	function setGlobalCookies() {
		global $wgRequest;
		$exp = time() + 86400 * 14; // 2 weeks
		setcookie( 'hidesnmessage', '0', $exp, '/' );
	}
}
