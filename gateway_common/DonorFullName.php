<?php

class DonorFullName implements StagingHelper {
	/*
	 * Seems more sane to do it this way than provide a single input box
	 * and try to parse out first_name and lname.
	 */
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$name_parts = array();
		if ( isset( $normalized['first_name'] ) ) {
			$name_parts[] = $normalized['first_name'];
		}
		if ( isset( $normalized['lname'] ) ) {
			$name_parts[] = $normalized['lname'];
		}
		$stagedData['full_name'] = implode( ' ', $name_parts );
	}
}
