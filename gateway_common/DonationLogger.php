<?php

/**
 * Logs information related to a donation attempt
 * TODO: wrap a PSR-3 logger indicated in the context
 */
class DonationLogger {

	/**
	* Log messages out to syslog (if configured), or the wfDebugLog
	* @param string $msg The message to log
	* @param int $log_level Should be one of the following:
	*      * LOG_EMERG - Actual meltdown in progress: Get everyone.
	*      * LOG_ALERT - Time to start paging people and failing things over
	*      * LOG_CRIT - Corrective action required, but you probably have some time.
	*      * LOG_ERR - Probably denotes a bug in the system.
	*      * LOG_WARNING - Not good, but will require eventual action to preserve stability
	*      * LOG_NOTICE - Unusual circumstances, but nothing imediately alarming
	*      * LOG_INFO - Nothing to see here. Business as usual.
	*      * LOG_DEBUG - Probably shouldn't use these unless we're in the process
	* of diagnosing a relatively esoteric problem that only happens in the prod
	* environment, which will require a settings change to start the data avalanche.
	* @param string $log_id_suffix Primarily used for shunting syslog messages off into alternative buckets.
	* @return null
	*/
	public static function log( $msg, $log_level = LOG_INFO, $log_id_suffix = '' ) {
		// Ensure our context is initialized
		DonationLoggerContext::initialize();
		if ( !DonationLoggerContext::$debug && $log_level === LOG_DEBUG ) {
			//stfu, then.
			return;
		}

		$identifier = DonationLoggerContext::$identifier . '_gateway' . $log_id_suffix;
		$msg = DonationLoggerContext::getLogMessagePrefix() . $msg;

		// if we're not using the syslog facility, use wfDebugLog
		if ( !DonationLoggerContext::$syslog ) {
			WmfFramework::debugLog( $identifier, $msg );
			return;
		}

		// otherwise, use syslogging
		openlog( $identifier, LOG_ODELAY, LOG_SYSLOG );
		$msg = str_replace( "\t", " ", $msg );
		syslog( $log_level, $msg );
		closelog();
	}
}
