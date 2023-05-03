<?php

class IngenicoReturnUrlHelper implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		if ( $adapter->isBatchProcessor() ) {
			// Only makes sense for real users.
			return;
		}
		if ( !empty( $normalized['return_url'] ) ) {
			$returnto = $normalized['return_url'];
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
				$queryArray = [ 'order_id' => $normalized['order_id'] ];
				$stagedData['return_url'] = wfAppendQuery( $returnto, $queryArray );
			}
		} else {
			// FIXME: An empty return_url should be handled by the result switcher instead.
			$stagedData['return_url'] = ResultPages::getThankYouPage( $adapter );
		}
	}
}
