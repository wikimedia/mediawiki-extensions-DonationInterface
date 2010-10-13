<?php 
/**
 * PayflowPro Gateway API extension
 */
 
class ApiPayflowProGateway extends ApiBase {
	
	/**
	 * API for PayflowProGateway extension
	 * 
	 * Parameters:
	 * 	dispatch: A string that maps to an API method.  Dispatchable API methods follow the naming convention
	 * 			dispatch_<requested dispatch method>
	 */
	public function execute() {
		// extract and validate the parameters
		$params = $this->extractRequestParams(); 
		$this->validateParams( $params );
		
		// route 'dispatch' requests to the appropriate method
		if ( strlen($params[ 'dispatch' ] )) {
			call_user_func_array( array( $this, $this->getDispatchMethod( $params[ 'dispatch' ])), array( $params ));
		}
	}
	
	public function getDescription() {
		return array(
			'Exposes API interaction with the PayflowPro Gateway extension.',
		);
	}
	
	public function getAllowedParams() {
		return array(
			'dispatch' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'tracking_data' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
		);
	}
	
	public function getParamDescription() {
		return array(
			'dispatch' => 'the API method from which to return data',
			'tracking_data' => 'A JSON formatted string of data to insert into contribution_tracking',
		);	
	}

	/**
	 * Validate parameters
	 * @params array Parameters extracted from $_POST
	 */
	public function validateParams( $params ) {
		
		/**
		 * If 'dispatch' is specified in the params, make sure the requested dispatch method exists
		 * 
		 * A 'dispatch' method is a string that maps to an API method whose name is dispatch_<requested dispatch method>
		 * so we need to make sure that method actually exists.  If not, die with an error.
		 */
		if ( isset( $params[ 'dispatch' ] ) && strlen( $params[ 'dispatch' ] )) {
			$method = $this->getDispatchMethod( $params[ 'dispatch' ] );
			if ( !method_exists( $this, $method )) {
				$this->dieUsage( "Invalid dispatch method <<<$method>>> passed to the PayflowPro Gatweay API.", 'unknown_method' );
			}
			
			// make sure we have tracking data for get_required_dynamic_form_elements
			if ( strtolower( $params[ 'dispatch' ] ) == 'get_required_dynamic_form_elements' ) {
				if ( !isset( $params[ 'tracking_data' ] )) {
					$this->dieUsage( "Dispatch method get_required_dynamic_form_elements requires 'tracking_data' parameter.", 'required_param' );	
				}
			}
		}
		
		// Validate tracking data
		if ( isset( $params[ 'tracking_data' ] )) {
			// Make sure tracking data is well formatted JSON
			if ( !json_decode( $params[ 'tracking_data' ] )) {
				$this->dieUsage( "Invalid JSON encoded tracking data", 'invalid_tracking');
			}
		}
	}
	
	public function getVersion() {
		return __CLASS__ . ': $Id$'; 
	}
	
	/**
	 * Construct the method name for a requested 'dispatch'
	 * @param string The method to dispatch requested in the API call
	 * @return string The method name corresponding to the requested dispatch request
	 */
	private function getDispatchMethod( $method ) {
		return 'dispatch_' . strtolower( $method );
	}
	
	/**
	 * Get the required dynamic form elements
	 * 
	 * The first time a PayflowProGateway credit card form is loaded, there are
	 * some dynamic elements that also need to be loaded, primarily for CSRF
	 * prevention and tracking data.  This will fetch the values for those
	 * elements.
	 */
	protected function dispatch_get_required_dynamic_form_elements( $params ) {
		global $wgPayflowGatewaySalt;
		
		// fetch the order_id
		require_once( 'includes/payflowUser.inc' );
		$payflow_data = payflowUser();
		$order_id = $payflow_data[ 'order_id' ];
		
		// fetch the CSRF prevention token and set it if it's not already set
		$token = PayflowProGateway::fnPayflowEditToken( $wgPayflowGatewaySalt );

		// retrieve and unpack the json encoded string of tracking data
		$tracking_data = json_decode( $params[ 'tracking_data' ], true );
		
		// ensure the utm_source is formatted correctly
		$utm_source_str = ( isset( $tracking_data[ 'utm_source' ] )) ? $tracking_data[ 'utm_source' ] : null;
		$utm_source_id = ( isset( $tracking_data[ 'utm_source_id' ] )) ? $tracking_data[ 'utm_source_id' ] : null;
		$tracking_data[ 'utm_source' ] = PayflowProGateway::getUtmSource( $utm_source_str, $utm_source_id );

		// fetch the contribution_tracking_id by inserting tracking data to contrib tracking table
		$contribution_tracking_id = PayflowProGateway::insertContributionTracking( $tracking_data );
		
		// this try/catch design pattern stolen from ClickTracking/ApiSpecialClickTracking.php
		try {
			// add dynamic elements to result object
			$this->getResult()->addValue( array( 'dynamic_form_elements' ), 'order_id', $order_id );
			$this->getResult()->addValue( array( 'dynamic_form_elements' ), 'token', $token );
			$this->getResult()->addValue( array( 'dynamic_form_elements' ), 'contribution_tracking_id', $contribution_tracking_id );
		} catch ( Exception $e ) {
			/* no result */
		}
	}
}