<?php
class BraintreeDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
				'payment_token' => $this->defineParam(),
			];
	}
}
