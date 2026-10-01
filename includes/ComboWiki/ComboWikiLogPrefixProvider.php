<?php

namespace MediaWiki\Extension\DonationInterface\ComboWiki;

use LogPrefixProvider;
use MediaWiki\Extension\DonationInterface\ComboWiki\Data\DonationDetails;

class ComboWikiLogPrefixProvider implements LogPrefixProvider {
	public function __construct( private readonly DonationDetails $donationDetails ) {
	}

	/**
	 * Example: combowiki: 844:844.1 storeDonationDetailsInSession: Data has been stored in session.
	 * Syntax: <contribution_tracking_id>:<order_id> <?functionName>: <message>
	 */
	public function getLogMessagePrefix(): string {
		$ctId = $this->donationDetails->getValue( 'contribution_tracking_id' );
		$orderId = $this->donationDetails->getValue( 'order_id' );
		return "$ctId:$orderId ";
	}
}
