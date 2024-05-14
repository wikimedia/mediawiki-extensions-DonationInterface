<?php
class AdyenDonationApi extends DonationApi {
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'bank_account_type' => $this->defineParam(),
			'color_depth' => $this->defineParam(),
			'encrypted_bank_account_number' => $this->defineParam(),
			'encrypted_bank_location_id' => $this->defineParam(),
			'encrypted_card_number' => $this->defineParam(),
			'encrypted_expiry_month' => $this->defineParam(),
			'encrypted_expiry_year' => $this->defineParam(),
			'encrypted_security_code' => $this->defineParam(),
			'issuer_id' => $this->defineParam(),
			'iban' => $this->defineParam(),
			'java_enabled' => $this->defineParam(),
			'payment_token' => $this->defineParam(),
			'screen_height' => $this->defineParam(),
			'screen_width' => $this->defineParam(),
			'time_zone_offset' => $this->defineParam(),
			// Note that these last two are only submitted in a variant for Japan forms.
			// When tests are over we should either make those fields permanent and keep
			// these parameters, or delete the variant and these parameters.
			'first_name_phonetic' => $this->defineParam(),
			'last_name_phonetic' => $this->defineParam(),
		];
	}
}
