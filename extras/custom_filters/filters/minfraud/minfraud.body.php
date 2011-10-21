<?php

/**
 * Wrapper for using minFraud extra as a custom filter
 *
 * Essentially runs minfraud query as the regular minFraud extra extension does
 * with slight modifications.  So all we do here is overload validate()
 * and add in some extra customFilters specific stuff.
 */
class Gateway_Extras_CustomFilters_MinFraud extends Gateway_Extras_MinFraud {

	static $instance;

	public function filter( &$custom_filter_object ) {
		// see if we can bypass minfraud
		if ( $this->can_bypass_minfraud() )
			return TRUE;

		$minfraud_query = $this->build_query( $this->gateway_adapter->getData() );
		$this->query_minfraud( $minfraud_query );
		$this->gateway_adapter->action = 'Filter';

		$custom_filter_object->risk_score += $this->minfraud_response['riskScore'];

		// Write the query/response to the log
		// @fixme this will cause the 'action' to be logged even though it's premature here
		$this->log_query( $minfraud_query );
		return TRUE;
	}

	static function onFilter( &$gateway_adapter, &$custom_filter_object ) {
		$gateway_adapter->debugarray[] = 'minfraud onFilter hook!';
		return self::singleton( $gateway_adapter )->filter( $custom_filter_object );
	}

	static function singleton( &$gateway_adapter ) {
		if ( !self::$instance ) {
			self::$instance = new self( $gateway_adapter );
		}
		return self::$instance;
	}

}
