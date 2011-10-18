<?php
/**
 * Wrapper for using minFraud extra as a custom filter
 *
 * Essentially runs minfraud query as the regular minFraud extra extension does
 * with slight modifications.  So all we do here is overload validate()
 * and add in some extra customFilters specific stuff.
 */

class PayflowProGateway_Extras_CustomFilters_MinFraud extends PayflowProGateway_Extras_MinFraud {
	static $instance;

	public function filter( &$custom_filter_object ) {
		$pfp_gateway_object =& $custom_filter_object->gateway_object;
		$data =& $custom_filter_object->gateway_data;

		// see if we can bypass minfraud
       	if ( $this->can_bypass_minfraud( $pfp_gateway_object, $data ) ) return TRUE;

        $minfraud_query = $this->build_query( $data );
        $this->query_minfraud( $minfraud_query );
       	$pfp_gateway_object->action = 'Filter';

		$custom_filter_object->risk_score += $this->minfraud_response['riskScore'];

		// Write the query/response to the log
		// @fixme this will cause the 'action' to be logged even though it's premature here
		$this->log_query( $minfraud_query, $pfp_gateway_object, $data );
		return TRUE;

	}

	static function onFilter( &$custom_filter_object ) {
		return self::singleton()->filter( $custom_filter_object );
	}

	static function singleton() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

}
