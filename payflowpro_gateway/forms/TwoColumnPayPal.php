<?php

class PayflowProGateway_Form_TwoColumnPayPal extends PayflowProGateway_Form_OneStepTwoColumn {

	public function __construct( &$form_data, &$form_errors ) {
		
		parent::__construct( $form_data, $form_errors );
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
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => $this->getNoCacheAction(), 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		
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
		$form .= $this->getCommentMessageField();
		
		//comment
		$form .= $this->getCommentField();
		
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