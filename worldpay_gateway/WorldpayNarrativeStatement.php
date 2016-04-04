<?php

class WorldpayNarrativeStatement implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		$stagedData['narrative_statement_1'] = WmfFramework::formatMessage(
			'donate_interface-statement',
			$normalized['contribution_tracking_id']
		);
	}
}
