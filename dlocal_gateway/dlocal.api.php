<?php
class DlocalDonationApi extends DonationApi {

	/**
	 * @var string
	 */
	public $gateway = DlocalAdapter::IDENTIFIER;

	/** @inheritDoc */
	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'payment_token' => $this->defineParam(),
			'upi_id' => $this->defineParam(),
		];
	}
}
