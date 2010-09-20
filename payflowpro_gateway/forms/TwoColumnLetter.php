<?php

class PayflowProGateway_Form_TwoColumnLetter extends PayflowProGateway_Form_TwoColumn {

        public function __construct( &$form_data, &$form_errors ) {
                global $wgOut, $wgScriptPath;
                parent::__construct( $form_data, $form_errors );

                // add form-specific css
                $wgOut->addExtensionStyle( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter.css');
	
		$this->updateHiddenFields();
        }

        public function generateFormBody() {
                global $wgOut, $wgRequest;
                $form = parent::generateBannerHeader();
                
                $form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_container'));
                
                $form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_form', 'class' => 'payflowpro_gateway-cc_form_column'));
                $form .= parent::generatePersonalContainerTop();
                $form .= parent::generatePersonalFields();
                $form .= Xml::closeElement( 'table' );
                $form .= Xml::closeElement( 'div' );
                $form .= parent::generatePaymentContainerTop();
                $form .= parent::generatePaymentFields();
                $form .= Xml::closeElement( 'table' );
               	$form .= Xml::closeElement( 'div' ); 
		$form .= $this->generateCommentFields();
		return $form;
        }

        public function generateFormSubmit() {
                global $wgRequest, $wgOut;
                $form = parent::generateFormSubmit();  

                $form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter', 'class' => 'payflowpro_gateway-cc_form_column'));

                $text_template = $wgRequest->getText( 'text_template' );
                if ( $wgRequest->getText( 'language' )) $text_template .= '/' . $wgRequest->getText( 'language' );
                
                $form .= ( strlen( $text_template )) ? $wgOut->parse( '{{'.$text_template.'}}' ) : '';
                $form .= Xml::closeElement( 'div' );
                
		$form .=Xml::closeElement( 'div' );
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
		$form .= '<td>' . Xml::label( wfMsg( 'donate_interface-anon-message' ), 'comment-option' );
		$form .= '</tr>';

		// email agreement
		$form .= '<tr>';
		$form .= '<td>' . Xml::check( 'opt', TRUE ) . '</td>';
		$form .= '<td>' . Xml::label( wfMsg( 'donate_interface-email-agreement' ), 'opt' ) . '</td>';
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
