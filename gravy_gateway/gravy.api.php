<?php
class GravyDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'payment_token' => $this->defineParam(),
			'card_suffix' => $this->defineParam(),
			'card_scheme' => $this->defineParam()
		];
	}
}
