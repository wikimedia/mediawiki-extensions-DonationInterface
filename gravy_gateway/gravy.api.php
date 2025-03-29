<?php
class GravyDonationApi extends DonationApi {
	/** @inheritDoc */
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'payment_token' => $this->defineParam(),
			'card_suffix' => $this->defineParam(),
			'card_scheme' => $this->defineParam()
		];
	}
}
