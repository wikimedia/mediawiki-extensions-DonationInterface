<?php

class ContributionTrackingProcessor {
	public static function contributionTrackingConnection() {
		return wfGetDB( DB_MASTER );
	}
}
