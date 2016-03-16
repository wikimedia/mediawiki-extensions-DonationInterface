<?php

class DonorFullName implements StagingHelper {
	/*
	 * Seems more sane to do it this way than provide a single input box
	 * and try to parse out fname and lname.
	 */
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$name_parts = array();
		if ( isset( $unstagedData['fname'] ) ) {
			$name_parts[] = $unstagedData['fname'];
		}
		if ( isset( $unstagedData['lname'] ) ) {
			$name_parts[] = $unstagedData['lname'];
		}
		$stagedData['full_name'] = implode( ' ', $name_parts );
	}

	// No-op.
	public function unstage( GatewayType $adapter, $stagedData, &$unstagedData ) {}
}
