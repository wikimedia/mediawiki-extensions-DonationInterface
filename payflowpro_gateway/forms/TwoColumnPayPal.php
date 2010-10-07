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

	protected function generatePersonalFields() {
		global $wgScriptPath, $wgPayflowGatewayTest;
		$scriptPath = "$wgScriptPath/extensions/DonationInterface/payflowpro_gateway/includes";
		$card_num = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'card_num' ] : '';
		$cvv = ( $wgPayflowGatewayTest ) ? $this->form_data[ 'cvv' ] : '';
		$form = '';
		
		// name	
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-name' ), 'fname' ) . '</td>';
		$form .= '<td>' . Xml::input( 'fname', '30', $this->form_data['fname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "First" )', 'maxlength' => '15', 'class' => 'required', 'id' => 'fname' ) ) .
			Xml::input( 'lname', '30', $this->form_data['lname'], array( 'type' => 'text', 'onfocus' => 'clearField( this, "Last" )', 'maxlength' => '15', 'id' => 'lname' ) ) . '<span class="creditcard-error-msg">' . '  ' . $this->form_errors['fname'] . '</span></td>';
		$form .= "</tr>";
		
		// email
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg( 'payflowpro_gateway-donor-email' ), 'emailAdd' ) . '</td>';
		$form .= '<td>' . Xml::input( 'emailAdd', '30', $this->form_data['email'], array( 'type' => 'text', 'maxlength' => '64', 'id' => 'emailAdd', 'class' => 'fullwidth' ) ) .
			'<span class="creditcard-error-msg">' . '  ' . $this->form_errors['emailAdd'] . '</span></td>';
		$form .= '</tr>';
		
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
		$comment_opt_value = ( $this->form_data[ 'numAttempt' ] ) ? $this->form_data[ 'comment-option' ] : true;
		$form .= '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'comment-option', $comment_opt_value );
		$form .= ' ' . Xml::label( wfMsg( 'donate_interface-anon-message' ), 'comment-option' ) . '</td>';
		$form .= '</tr>';

		// email agreement
		$email_opt_value = ( $this->form_data[ 'numAttempt' ]) ? $this->form_data[ 'email-opt' ] : true;
		$form .= '<tr>';
		$form .= '<td class="check-option" colspan="2">' . Xml::check( 'email-opt', $email_opt_value );
		$form .= ' ';
		// put the label inside Xml::openElement so any HTML in the msg might get rendered (right, Germany?)
		$form .= Xml::openElement( 'label', array( 'for' => 'email-opt' ));
		$form .= wfMsg( 'donate_interface-email-agreement' );
		$form .= Xml::closeElement( 'label' );
		$form .= '</td>';
		$form .= '</tr>';
		
		// amount
		$form .= $this->getAmountField();
		
		// PayPal button
		$form .= '<tr>';
		$form .= '<td class="paypal-button" colspan="2">';
		$form .= Xml::tags( 'div',
				array(),
				'<img src="'.$scriptPath.'/donate_with_paypal.gif"/>'
			);
		$form .= '</td>';
		$form .= '</tr>';
		
		return $form;
	}

}