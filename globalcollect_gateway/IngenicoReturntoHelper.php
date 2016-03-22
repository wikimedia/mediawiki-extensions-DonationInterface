<?php

class IngenicoReturntoHelper implements StagingHelper {
	public function stage( GatewayType $adapter, $unstagedData, &$stagedData ) {
		if ( !empty( $unstagedData['returnto'] ) ) {
			$returnto = $unstagedData['returnto'];
		} else {
			$returnto = '';
		}

		if ( isset( $unstagedData['payment_method'] )
			&& $unstagedData['payment_method'] === 'cc'
		) {
			// Add order ID to the returnto URL, only if it's not already there.
			//TODO: This needs to be more robust (like actually pulling the
			//qstring keys, resetting the values, and putting it all back)
			//but for now it'll keep us alive.
			if ( $adapter->getOrderIDMeta( 'generate' )
				&& !empty( $returnto )
				&& !strpos( $returnto, 'order_id' )
			) {
				$queryArray = array( 'order_id' => $unstagedData['order_id'] );
				$stagedData['returnto'] = wfAppendQuery( $returnto, $queryArray );
			}
		} else {
			// FIXME: An empty returnto should be handled by the result switcher instead.
			$stagedData['returnto'] = ResultPages::getThankYouPage( $adapter );
		}
	}
}
