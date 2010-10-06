<?php

class PayflowProGateway_Form_TwoColumnLetter extends PayflowProGateway_Form_TwoColumn {

	public function __construct( &$form_data, &$form_errors ) {
		global $wgOut, $wgScriptPath;
		
		// set the path to css, before the parent constructor is called, checking to make sure some child class hasn't already set this
		if ( !strlen( $this->getStylePath())) {
			$this->setStylePath( $wgScriptPath . '/extensions/DonationInterface/payflowpro_gateway/forms/css/TwoColumnLetter.css' );
		}
			
		parent::__construct( $form_data, $form_errors );
		
		// update the list of hidden fields we need to use in this form.
		$this->updateHiddenFields();
	}

	public function generateFormStart() {
		global $wgOut, $wgRequest;
		$form = parent::generateBannerHeader();
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_container'));
		
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_form', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= Xml::Tags( 'p', array( 'id' => 'payflowpro_gateway-cc_otherways' ), wfMsg( 'payflowpro_gateway-paypal' ));
		
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
			$form .= Xml::closeElement( 'div' );  // close div#mw-payflow-general-error
		}

		// open form
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-creditcard-form' ) );
		
		// Xml::element seems to convert html to htmlentities
		$form .= "<p class='creditcard-error-msg'>" . $this->form_errors['retryMsg'] . "</p>";
		$form .= Xml::openElement( 'form', array( 'name' => 'payment', 'method' => 'post', 'action' => '', 'onsubmit' => 'return validate_form(this)', 'autocomplete' => 'off' ) );
		
		$form .= parent::generatePersonalContainer();
		$form .= $this->generateCommentFields();
		return $form;
	}
        
	public function generateFormEnd() {
		global $wgRequest, $wgOut;
		$form = '';
		
		$form .= $this->generateFormClose();

		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter', 'class' => 'payflowpro_gateway-cc_form_column'));
		$form .= Xml::openElement( 'div', array( 'id' => 'payflowpro_gateway-cc_form_letter_inside' ));
		
		$text_template = $wgRequest->getText( 'text_template' );
		// if the user has uselang set, honor that, otherwise default to the language set for the form defined by 'language' in the query string
		if ( $wgRequest->getText( 'language' )) $text_template .= '/' . $this->form_data[ 'language' ];
		
		$template = ( strlen( $text_template )) ? $wgOut->parse( '{{'.$text_template.'}}' ) : '';
		// if the template doesn't exist, prevent the display of the red link
		if ( preg_match( '/redlink\=1/', $template )) $template = NULL;
		$form .= $template;
		
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-cc_form_letter
		$form .= Xml::closeElement( 'div' ); // close div#payflowpro_gateway-cc_form_letter_inside
		return $form;
	}

	public function generateCommentFields() {
		global $wgRequest;
		
		$form = Xml::openElement( 'div', array( 'class' => 'payflow-cc-form-section', 'id' => 'payflowpro_gateway-comment_form' ));
		$form .= Xml::tags( 'h3', array( 'class' => 'payflow-cc-form-header', 'id' => 'payflow-cc-form-header-comments' ), wfMsg( 'donate_interface-comment-title' ));
		$form .= Xml::tags( 'p', array(), wfMsg( 'donate_interface-comment-message' ));
		$form .= Xml::openElement( 'table', array( 'id' => 'payflow-table-comment' ) );

		//comment
		$form .= '<tr>';
		$form .= '<td class="label">' . Xml::label( wfMsg('payflowpro_gateway-comment'), 'comment' ) . '</td>';
		$form .= '<td class="comment-field">' . Xml::input( 'comment', '30', $this->form_data[ 'comment' ], array( 'type' => 'text', 'maxlength' => '200', 'class' => 'fullwidth' )) . '</td>';
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

		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'div' );	
		return $form;
	}

	/**
	 * Update hidden fields to not set any comment-related fields
	 */
	public function updateHiddenFields() {
		$hidden_fields = $this->getHiddenFields();

		// make sure that the below elements are not set in the hidden fields
		$not_needed = array( 'comment-option', 'email-opt', 'comment' );

		foreach ( $not_needed as $field ) {
			unset( $hidden_fields[ $field ] );
		}
		
		$this->setHiddenFields( $hidden_fields );
	}
	
	/**
	 * Generate form closing elements
	 */
	public function generateFormClose() {
		$form = '';
		// add hidden fields			
		$hidden_fields = $this->getHiddenFields();
		foreach ( $hidden_fields as $field => $value ) {
			$form .= Xml::hidden( $field, $value );
		}
			
		$form .= Xml::closeElement( 'form' ); // close form 'payment'
		$form .= <<<EOT
<script type="text/javascript">
var fname = document.getElementById('fname');
var lname = document.getElementById('lname');
if (fname.value == '') {
	fname.style.color = '#999999';
	fname.value = 'First';
}
if (lname.value == '') {
	lname.style.color = '#999999';
	lname.value = 'Last';
}
</script>
EOT;
		//$form .= $this->generateDonationFooter();

		$form .= Xml::closeElement( 'div' ); //close div#mw-creditcard
		$form .= Xml::closeElement( 'div' ); //close div#payflowpro_gateway-cc_form_form
		$form .= Xml::closeElement( 'div' ); //close div#payflowpro_gateway-cc_form_container
		return $form;
	}
}
