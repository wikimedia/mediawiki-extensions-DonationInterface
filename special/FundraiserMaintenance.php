<?php

/**
 *
 * -- License --
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

class FundraiserMaintenance extends UnlistedSpecialPage {

	public function __construct() {
		parent::__construct( 'FundraiserMaintenance' );
	}

	public function execute( $sub ) {
		global $wgDonationInterfaceProblemsEmail;

		$output = $this->getOutput();
		$this->setHeaders();

		$output->setStatusCode( 503 );

		$this->outputHeader();

		$output->setPageTitle( wfMessage( 'donate_interface-fundraiser-maintenance-header' ) );

		// Now do whatever we have to do to output the content in $outContent
		// Hide unneeded interface elements
		$output->addModules( 'donationInterface.skinOverride' );

		$output->addHTML(
			"<p>" . wfMessage( 'donate_interface-fundraiser-maintenance-notice' )
				->rawparams(
					"<a href='mailto:$wgDonationInterfaceProblemsEmail'>$wgDonationInterfaceProblemsEmail</a>"
				) . "<p>"
		);
	}
}
