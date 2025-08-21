<?php
class GravyDonationApi extends DonationApi {

	/**
	 * @var string
	 */
	public $gateway = GravyAdapter::IDENTIFIER;

	/** @inheritDoc */
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'payment_token' => $this->defineParam(),
			'card_suffix' => $this->defineParam(),
			'card_scheme' => $this->defineParam(),
			'color_depth' => $this->defineParam(),
			'screen_height' => $this->defineParam(),
			'screen_width' => $this->defineParam(),
			'time_zone_offset' => $this->defineParam(),
		];
	}
}
