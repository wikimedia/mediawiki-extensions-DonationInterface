<?php

/**
 * Allowed values for final status of a payment attempt
 */
class FinalStatus {
	const COMPLETE = 'complete';
	const FAILED = 'failed';
	const PENDING = 'pending';
	const PENDING_POKE = 'pending-poke';
	const REVISED = 'revised';
	const REFUNDED = 'refunded';
	const CANCELLED = 'cancelled';
}
