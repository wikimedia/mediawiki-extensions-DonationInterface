<?php

class PayflowProGateway_Form_TwoColumnPayPal extends PayflowProGateway_Form_TwoColumn {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		
		parent::__construct( $form_data, $form_errors );
		
		// we only want to load this JS if the form is being rendered
		$wgOut->addHeadItem( 'validatescript', '<script type="text/javascript" src="' . 
				     $wgScriptPath . 
 				     '/extensions/DonationInterface/payflowpro_gateway/validate_input.js?283"></script>' );
	}
	
	public function generateFormStart() {
		global $wgPayflowGatewayHeader, $wgPayflwGatewayTest, $wgOut;
		$form = $this->generateBannerHeader();
		
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard' ) ); 
		
		// provide a place at the top of the form for displaying general messages
		if ( $this->form_errors['general'] ) {
			$form .= Xml::openElement( 'div', array( 'id' => 'mw-payflow-general-error' ));
			if ( is_array( $this->form_errors['general'] )) {
				foreach ( $this->form_errors['general'] as $this->form_errors_msg ) {
					$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
				}
			} else {
				$form .= Xml::tags( 'p', array( 'class' => 'creditcard-error-msg' ), $this->form_errors_msg );
			}
			$form .= Xml::closeElement( 'div' );
		}

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );
		
		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		
		$form .= Xml::openElement( 'div', array( 'id' => 'left-column', 'class' => 'payflow-cc-form-section'));
		$form .= $this->generatePersonalContainer();
		$form .= Xml::closeElement( 'div' ); // close div#left-column
		
		$form .= Xml::openElement( 'div', array( 'id' => 'right-column', 'class' => 'payflow-cc-form-section' ));
		$form .= $this->generatePaymentContainer();
		
		return $form;
	}
	
	protected function generatePersonalContainer() {
		global $wgRequest;
		$form = '';
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-personal-info' ));			;
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header','id' => 'payflow-cc-form-header-personal' ), wfMsg( 'payflowpro_gateway-make-your-donation' ));
		$sourceId = $wgRequest->getText( 'utm_source_id', 13 );
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-donor' ) );
		
		$form .= $this->generatePersonalFields();
		
		$form .= Xml::closeElement( 'table' ); // close table#payflow-table-donor
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-personal-info

		return $form;
	}

	protected function generatePersonalFields() {
		global $wgPayflowGatewayPaypalURL;
		$form = '';
		
		// name	
		$form .= $this->getNameField();
		
		// email
		$form .= $this->getEmailField();
		
		//comment message
		$form .= '<tr>';
		$form .= '<td colspan="2">';
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ));
		$form .= '</td>';
		$form .= '</tr>';
		
		//comment
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg('payflowpro_gateway-comment'), 'comment' ) . '</td>';
		$form .= '<td>' . Xml::input( 'comment', '30', $this->form_data[ 'comment' ], array( 'type' => 'text', 'maxlength' => '200', 'class' => 'fullwidth' )) . '</td>';
		$form .= '</tr>';
		
		// anonymous
		$form .= $this->getCommentOptionField();

		// email agreement
		$form .= $this->getEmailOptField();
		
		// amount
		$form .= $this->getAmountField();
		
		// PayPal button
		if ( strlen( $wgPayflowGatewayPaypalURL )) {
			$form .= $this->getPaypalButton();
		}
		
		return $form;
	}

}