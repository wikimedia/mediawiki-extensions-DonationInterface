<?php

/**
 * Constants to indicate the status of responses from payment gateways
 */
class ResponseCodes {
	const BAD_FORMAT = 'BAD_FORMAT';
	const BAD_SIGNATURE = 'BAD_SIGNATURE';
	const DUPLICATE_ORDER_ID = 'DUPLICATE_ORDER_ID';
	const MISSING_TRANSACTION_ID = 'MISSING_TRANSACTION_ID';
	const MISSING_REQUIRED_DATA = 'MISSING_REQUIRED_DATA';
	const NO_RESPONSE = 'NO_RESPONSE';
	const UNKNOWN = 'UNKNOWN';
}
