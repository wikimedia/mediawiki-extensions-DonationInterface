<?php

class DonorPortalForm extends EmailForm {
	protected function getValidParams(): array {
		return [
			'showLogin',
		];
	}
}
