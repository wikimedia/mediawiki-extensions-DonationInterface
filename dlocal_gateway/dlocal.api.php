<?php
class DlocalDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'payment_token' => $this->defineParam(),
			'upi_id' => $this->defineParam(),
		];
	}
}
