<?php

interface LogPrefixProvider {
	/**
	 * @return string
	 */
	public function getLogMessagePrefix();
}
