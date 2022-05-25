<?php

use MediaWiki\MediaWikiServices;
use Smashpig\PaymentData\FinalStatus;

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
		$params = [];
		if ( !$this->gateway->session_hasDonorData() ) {
			foreach ( DonationData::getRetryFields() as $field ) {
				if ( isset( $data[$field] ) ) {
					$params[$field] = $data[$field];
				}
			}
		}
		$data['retry_link'] = GatewayChooser::buildGatewayPageUrl( $this->gateway->getIdentifier(), $params, MediaWikiServices::getInstance()->getMainConfig() );
	}

	protected function addMessageParameters( &$data ) {
		// Add otherways_url
		$data += $this->getUrlsAndEmails();
		global $wgDonationInterfaceFundraiserMaintenance;
		// set the appropriate header
		if ( $this->gateway->getFinalStatus() === FinalStatus::CANCELLED ) {
			$data['header_key'] = 'donate_interface-donation-cancelled-header';
			$data['error-cancel'] = true;
		} elseif ( $data['payment_method'] === 'cc' ) {
			$data['header_key'] = 'php-response-declined';
			$data['error-cc'] = true;
		} elseif ( $wgDonationInterfaceFundraiserMaintenance === true ) {
			$data['header_key'] = 'donate_interface-maintenance-notice';
			$data['maintenance'] = true;
		} else {
			$data['header_key'] = 'donate_interface-error-msg-general';
			$data['error-default'] = true;
		}
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
