<?php
class BraintreeDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
				'payment_token' => $this->defineParam(),
				'user_name' => $this->defineParam(),
				'customer_id' => $this->defineParam(),
				'device_data' => $this->defineParam(),
			];
	}
}
