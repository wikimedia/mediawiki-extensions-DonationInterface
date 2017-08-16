<?php

class IngenicoReturntoHelper implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( $adapter->isBatchProcessor() ) {
			// Only makes sense for real users.
			return;
		}
		if ( !empty( $normalized['returnto'] ) ) {
			$returnto = $normalized['returnto'];
		} else {
			$returnto = '';
		}

		if ( isset( $normalized['payment_method'] )
			&& $normalized['payment_method'] === 'cc'
		) {
			// Add order ID to the returnto URL, only if it's not already there.
			// TODO: This needs to be more robust (like actually pulling the
			// qstring keys, resetting the values, and putting it all back)
			// but for now it'll keep us alive.
			if ( $adapter->getOrderIDMeta( 'generate' )
				&& !empty( $returnto )
				&& !strpos( $returnto, 'order_id' )
			) {
				$queryArray = array( 'order_id' => $normalized['order_id'] );
				$stagedData['returnto'] = wfAppendQuery( $returnto, $queryArray );
			}
		} else {
			// FIXME: An empty returnto should be handled by the result switcher instead.
			$stagedData['returnto'] = ResultPages::getThankYouPage( $adapter );
		}
	}
}
