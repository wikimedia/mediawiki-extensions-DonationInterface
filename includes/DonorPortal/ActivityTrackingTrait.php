<?php

namespace MediaWiki\Extension\DonationInterface\DonorPortal;

trait ActivityTrackingTrait {

	/**
	 * Any class using this trait needs to implement getRequest. SpecialPage and ApiBase both do.
	 * @return \MediaWiki\Request\WebRequest
	 */
	abstract public function getRequest();

	protected function getTrackingParametersWithPrefix(): array {
		return $this->getRequest()->getValues( 'wmf_campaign', 'wmf_medium', 'wmf_source' );
	}

	protected function getTrackingParametersWithoutPrefix(): array {
		$paramsWithPrefix = $this->getTrackingParametersWithPrefix();
		$paramsWithoutPrefix = [];
		foreach ( $paramsWithPrefix as $name => $value ) {
			$paramsWithoutPrefix[ substr( $name, 4 ) ] = $value;
		}
		return $paramsWithoutPrefix;
	}
}
