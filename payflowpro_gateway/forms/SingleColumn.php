<?php

class PayflowProGateway_Form_SingleColumn extends PayflowProGateway_Form_TwoColumnLetter {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgScriptPath;

		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath())) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/SingleColumn.css' );
		}

		parent::__construct( $form_data, $form_errors );
	}

	public function generateFormEnd() {
		return $this->generateFormClose();
	}
}
