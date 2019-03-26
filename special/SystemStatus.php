<?php
/**
 * SystemStatus will eventually be a page that runs some internal tests and reports back
 * for Nagios/Icinga, etc.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */
class SystemStatus extends UnlistedSpecialPage {

	function __construct() {
		parent::__construct( 'SystemStatus' );
	}

	function execute( $par ) {
		global $wgDonationInterfaceEnableSystemStatus;

		if ( !$wgDonationInterfaceEnableSystemStatus ) {
			throw new BadTitleError();
		}

		// Right now we just need something that doesn't end up creating
		// contribution_ids for testing.
		echo "<pre>OK</pre>";
		die();
	}

}
