<?php

class Gateway_Form_Handlebars extends Gateway_Form {
	protected $topLevelForm;

	public function __construct( GatewayAdapter $gateway ) {
		parent::__construct( $gateway );

		// TODO: Don't hardcode like this.
		global $wgDonationInterfaceTemplate;
		$this->topLevelForm = $wgDonationInterfaceTemplate;
	}

	/**
	 * Return the HTML form with data added
	 */
	public function getForm() {
		$data = $this->gateway->getData_Unstaged_Escaped();

		$template = file_get_contents( $this->topLevelForm );
		if ( !$template ) {
			throw new Exception( "Template file unavailable: [{$this->topLevelForm}]" );
		}

		// TODO: Use MW-core implementation, once we're on REL1_25.
		$code = LightnCandy::compile(
			$template,
			array(
				'flags' => LightnCandy::FLAG_ERROR_EXCEPTION,
			)
		);
		if ( !$code ) {
			throw new Exception( 'Couldn\'t compile template!' );
		}
		if ( substr( $code, 0, 5 ) === '<?php' ) {
			$code = substr( $code, 5 );
		}
		$renderer = eval( $code );
		if ( !is_callable( $renderer ) ) {
			throw new Exception( 'Can\'t run compiled template!' );
		}

		$html = call_user_func( $renderer, $data, array() );

		return $html;
	}
}
