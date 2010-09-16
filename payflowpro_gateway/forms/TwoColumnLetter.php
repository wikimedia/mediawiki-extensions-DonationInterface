<?php

class PayflowProGateway_Form_TwoColumnLetter extends PayflowProGateway_Form_TwoColumn {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		parent::__construct( $form_data, $form_errors );

		// add form-specific css
		$wgOut->addExtensionStyle( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter.css');
	}

	public function generateFormBody() {
		global $wgOut, $wgRequest;
		$form = parent::generateBannerHeader();
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_container'));
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter', 'class' => 'payflowpro_gateway-cc_form_column'));

		$text_template = $wgRequest->getText( 'text_template' );
		if ( $wgRequest->getText( 'language' )) $text_template .= '/' . $wgRequest->getText( 'language' );
		
		$form .= ( strlen( $text_template )) ? $wgOut->parse( '{{'.$text_template.'}}' ) : '';
		$form .= Xml::closeElement( 'div' );
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_form', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= parent::generatePersonalContainerTop();
		$form .= parent::generatePersonalFields();
		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'div' );
		$form .= parent::generatePaymentContainerTop();
		$form .= parent::generatePaymentFields();
		$form .= Xml::closeElement( 'table' );
		return $form;
	}

	public function generateFormSubmit() {
		$form .= parent::generateFormSubmit();	

		$form .=Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		return $form;
	}
}
