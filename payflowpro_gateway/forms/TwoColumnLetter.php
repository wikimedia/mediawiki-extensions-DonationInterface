<?php

class PayflowProGateway_Form_TwoColumnLetter extends PayflowProGateway_Form_TwoColumn {

		public function __construct( &$form_data, &$form_errors ) {
                global $wgOut, $wgScriptPath;
                parent::__construct( $form_data, $form_errors );

                // add form-specific css
                $wgOut->addExtensionStyle( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter.css');
	
		$this->updateHiddenFields();
        }

	public function generateFormStart() {
		global $wgOut, $wgRequest;
		$form = parent::generateBannerHeader();
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_container'));
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_form', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= Xml::Tags( 'p', array( 'id' => 'payflowpro_gateway-cc_otherways' ), wfMsg( 'payflowpro_gateway-otherways' ));
		$form .= parent::generatePersonalContainer();
		$form .= parent::generatePaymentContainer();
		$form .= $this->generateCommentFields();
		return $form;
	}
        
	public function generateFormEnd() {
		global $wgRequest, $wgOut;
		$form = '';
		// add hidden fields			
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Xml::hidden( $field, $value );
		}
			
		$form .= Xml::closeElement( 'form' );

		$form .= $this->generateDonationFooter();

		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );

		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter_inside' ));
		$text_template = $wgRequest->getText( 'text_template' );
		if ( $wgRequest->getText( 'language' )) $text_template .= '/' . $wgRequest->getText( 'language' );
		
		$form .= ( strlen( $text_template )) ? $wgOut->parse( '{{'.$text_template.'}}' ) : '';
		$form .= Xml::closeElement( 'div' );
		$form .= Xml::closeElement( 'div' );
		return $form;
	}

	public function generateCommentFields() {
		$form = Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-comment_form' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-comments' ), wfMsg( 'donate_interface-comment-title' ));
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-comment' ) );

		//comment
		$form .= '<tr>';
		$form .= '<td>' . Xml::label( wfMsg('donate_interface-comment-label'), 'comment' ) . '</td>';
		$form .= '<td>' . Xml::input( 'comment', '30', '', array( 'maxlength' => '200' )) . '</td>';
		$form .= '</tr>';
		
		// anonymous
		$form .= '<tr>';
		$form .= '<td>' . Xml::check( 'comment-option', TRUE ) . '</td>';
		$form .= '<td>' . Xml::label( wfMsg( 'donate_interface-anon-message' ), 'comment-option' ) . '</td>';
		$form .= '</tr>';

		// email agreement
		$form .= '<tr>';
		$form .= '<td>' . Xml::check( 'email-opt', TRUE ) . '</td>';
		$form .= '<td>' . Xml::label( wfMsg( 'donate_interface-email-agreement' ), 'email-opt' ) . '</td>';
		$form .= '</tr>';

		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'div' );	
		return $form;
	}

	/**
	 * Update hidden fields to not set any comment-related fields
	 */
	public function updateHiddenFields() {
		$hidden_fields = $this->getHiddenFields();
		$not_needed = array( 'comment-option', 'email', 'comment' );
		foreach ( $not_needed as $field ) {
			unset( $hidden_fields[ $field ] );
		}
		$this->setHiddenFields( $hidden_fields );
	}
}
