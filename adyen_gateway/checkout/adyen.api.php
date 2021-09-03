<?php
class AdyenDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'color_depth' => $this->defineParam(),
			'encrypted_card_number' => $this->defineParam(),
			'encrypted_expiration_month' => $this->defineParam(),
			'encrypted_expiration_year' => $this->defineParam(),
			'encrypted_security_code' => $this->defineParam(),
			'issuer_id' => $this->defineParam(),
			'java_enabled' => $this->defineParam(),
			'screen_height' => $this->defineParam(),
			'screen_width' => $this->defineParam(),
			'time_zone_offset' => $this->defineParam(),
			'gateway_session_id' => $this->defineParam(),
		];
	}
}
