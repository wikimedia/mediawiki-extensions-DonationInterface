<?php
/**
 * SystemStatus will eventually be a page that runs some internal tests and reports back
 * for Nagios/Icinga, etc.
 *
 * @author Peter Gehres <pgehres@wikimedia.org>
 */
class SystemStatus extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'SystemStatus' );
	}

	public function execute( $par ) {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		if ( !$this->getConfig()->get( 'DonationInterfaceEnableSystemStatus' ) ) {
			throw new BadTitleError();
		}

		// Right now we just need something that doesn't end up creating
		// contribution_ids for testing.
		echo "<pre>OK</pre>";
		die();
	}

}
