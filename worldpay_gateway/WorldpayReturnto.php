<?php

class WorldpayReturnto implements StagingHelper {
	public function stage( GatewayType $adapter, $normalized, &$stagedData ) {
		global $wgServer, $wgArticlePath;

		// Rebuild the url with the token param.

		$arr_url = parse_url(
			$wgServer . str_replace(
				'$1',
				'Special:WorldpayGatewayResult',
				$wgArticlePath
			)
		);

		$query = '';
		$first = true;
		if ( isset( $arr_url['query'] ) ) {
			parse_str( $arr_url['query'], $arr_query );
		}
		// Worldpay decodes encoded URL unsafe characters in XML before storage,
		// and sends them back that way in the return header.  So anything you
		// want to be returned encoded must be double-encoded[1], for example
		// %2526 will get returned as %26 and decoded to &, while %26 will get
		// returned as & and treated as a query string separator.

		// Additionally a properly encoded & will make their server respond
		// MessageCode 302 (which means 'unavailable') unless it is wrapped in
		// CDATA tags because godonlyknows
		$arr_query['token'] = rawurlencode( $adapter->token_getSaltedSessionToken() );
		if ( !empty( $normalized['ffname'] ) ) {
			$arr_query['ffname'] = rawurlencode( $normalized['ffname'] );
		}
		if ( !empty( $normalized['amount'] ) ) {
			$arr_query['amount'] = rawurlencode( $normalized['amount'] );
		}
		foreach ( $arr_query as $key => $val ) {
			$query .= ( $first ? '?' : '&' ) . $key . '=' . $val;
			$first = false;
		}

		$stagedData['returnto'] = rawurlencode( // [1]
			$arr_url['scheme'] .  '://' .
			$arr_url['host'] .
			$arr_url['path'] .
			$query
		);
	}
}
