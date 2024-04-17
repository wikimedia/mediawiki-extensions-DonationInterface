<?php

namespace MediaWiki\Extension\DonationInterface\Api;

use Psr\Log\LoggerInterface;
use RecurUpgrade;

class ApiRecurUpgradeClientError extends ApiClientErrorBase {

	protected function getLogger( array $sessionData ): LoggerInterface {
		return RecurUpgrade::getLogger();
	}
}
