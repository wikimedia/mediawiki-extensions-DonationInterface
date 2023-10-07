<?php
class BraintreeDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
				'payment_token' => $this->defineParam(),
				'user_name' => $this->defineParam(),
				'customer_id' => $this->defineParam(),
				'processor_contact_id' => $this->defineParam(),
				'gateway_session_id' => $this->defineParam(),
				'device_data' => $this->defineParam(),
			];
	}
}
