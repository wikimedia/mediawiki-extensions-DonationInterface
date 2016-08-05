<?php

class ContributionTrackingProcessor {
	static function contributionTrackingConnection() {
		return wfGetDB( DB_MASTER );
	}
}
