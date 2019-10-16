<?php

/**
 * Renders error forms from Mustache templates
 */
class MustacheErrorForm extends Gateway_Form_Mustache {

	/**
	 * Return the rendered HTML form, using template parameters from the gateway object
	 *
	 * @return string
	 * @throws RuntimeException
	 */
	public function getForm() {
		$data = $this->gateway->getData_Unstaged_Escaped();
		self::$country = $data['country'];

		$this->addMessageParameters( $data );
		$this->addRetryLink( $data );

		$options = [
			'helpers' => [
				'l10n' => 'Gateway_Form_Mustache::l10n',
			],
			'basedir' => [ self::$baseDir ],
			'fileext' => self::EXTENSION,
		];
		return MustacheHelper::render( $this->getTopLevelTemplate(), $data, $options );
	}

	protected function addRetryLink( &$data ) {
		// add data we're going to need for the error page!
		$back_form = $this->gateway->session_getLastFormName();

		$params = [
			'gateway' => $this->gateway->getIdentifier()
		];
		if ( !$this->gateway->session_hasDonorData() ) {
			foreach ( DonationData::getRetryFields() as $field ) {
				if ( isset( $data[$field] ) ) {
					$params[$field] = $data[$field];
				}
			}
		}
		$data['ffname_retry'] = GatewayFormChooser::buildPaymentsFormURL( $back_form, $params );
	}

	protected function addMessageParameters( &$data ) {
		// Add otherways_url
		$data += $this->getUrlsAndEmails();
		// set the appropriate header
		$headers = [
			'error-cc' => 'php-response-declined',
			'error-cancel' => 'donate_interface-donation-cancelled-header',
			'error-default' => 'donate_interface-error-msg-general',
			'error-noform' => 'donate_interface-error-msg-general',
			'maintenance' => 'donate_interface-maintenance-notice',
		];
		$form = $data['ffname'];
		$data['header_key'] = $headers[$form];
		$data[$form] = true;
	}

	protected function getTopLevelTemplate() {
		return $this->gateway->getGlobal( 'ErrorTemplate' );
	}

	/**
	 * Override the parent implementation to get rid of any payment-method-specific CSS
	 * @return array
	 */
	public function getStyleModules() {
		return [ 'ext.donationInterface.mustache.styles' ];
	}

	/**
	 * Override the parent implementation to get rid of any payment-method-specific JavaScript
	 * @return array
	 */
	public function getResources() {
		return [];
	}
}
