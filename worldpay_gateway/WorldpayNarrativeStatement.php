<?php

class WorldpayNarrativeStatement implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		$stagedData['narrative_statement_1'] = WmfFramework::formatMessage(
			'donate_interface-statement',
			$unstagedData['contribution_tracking_id']
		);
	}
}
