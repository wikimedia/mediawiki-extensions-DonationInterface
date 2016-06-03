<?php

/**
 * Renders error forms from Mustache templates
 */
class MustacheErrorForm extends Gateway_Form_Mustache {

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throw RuntimeException
	 */
	public function getForm() {
		$data = $this->gateway->getData_Unstaged_Escaped();
		self::$country = $data['country'];

		$this->addMessageParameters( $data );
		$this->addRetryLink( $data );

		$options = array(
			'helpers' => array(
				'l10n' => 'Gateway_Form_Mustache::l10n',
			),
			'basedir' => array( self::$baseDir ),
			'fileext' => self::EXTENSION,
		);
		return self::render( $this->getTopLevelTemplate(), $data, $options );
	}

	protected function addRetryLink( &$data ) {
		//add data we're going to need for the error page!
		$back_form = $this->gateway->session_getLastFormName();

		$params = array (
			'gateway' => $this->gateway->getIdentifier()
		);
		if ( !$this->gateway->session_hasDonorData() ) {
			$preserve = $this->gateway->getRetryData();
			$params = array_merge( $preserve, $params );
		}
		$data['ffname_retry'] = GatewayFormChooser::buildPaymentsFormURL( $back_form, $params ) ;
	}
	
	protected function addMessageParameters( &$data ) {
		$data['problems_email'] = $this->gateway->getGlobal( 'ProblemsEmail' );
		// FIXME: rename getCancelPage
		$data['ways_to_give'] = ResultPages::getCancelPage( $this->gateway );
		// set the appropriate header
		$headers = array(
			'error-cc' => 'php-response-declined',
			'error-default' => 'donate_interface-error-msg-general',
			'error-noform' => 'donate_interface-error-msg-general',
			'maintenance' => 'donate_interface-maintenance-notice',
		);
		$form = $data['ffname'];
		$data['header_key'] = $headers[$form];
		$data[$form] = true;
	}

	protected function getTopLevelTemplate() {
		return $this->gateway->getGlobal( 'ErrorTemplate' );
	}
}
