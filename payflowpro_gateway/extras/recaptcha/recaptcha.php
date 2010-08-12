<?php
/**
 * Validates a transaction against MaxMind's minFraud service
 *
 * To install:
 *	require_once( "$IP/extensions/recaptcha/ReCaptcha.php" );
 *
 * You will need to get reCaptcha public/private keys (http://www.google.com/recaptcha/whyrecaptcha)
 * In LocalSettings.php:
 *	$recaptcha_public_key = '<key>';
 *  $recaptcha_private_key = '<key>';
 *  $wgPayflowCaptcha = new $wgCaptchaClass;
 */

require_once( dirname( __FILE__ ) . "/../extras.php" );
class PayflowProGateway_Extras_reCaptcha extends PayflowProGateway_Extras {
	/**
	 * Handle the challenge logic
	 */
	public function challenge( &$pfp_gateway_object, &$data ) {
		// if captcha posted, validate
		if ( isset( $_POST[ 'recaptcha_response_field' ] )) { 
			if ( $this->validate_captcha() ){
				// if validated, update the action and move on
				$this->log( $data[ 'contribution_tracking_id' ], 'Captcha passed' );
				$pfp_gateway_object->action = "process";
				return TRUE;
			} else {
				$this->log( $data[ 'contribution_tracking_id' ], 'Captcha failed' );
			}
		}
		// display captcha
		$this->display_captcha( &$pfp_gateway_object, &$data );
		return TRUE;
	}

	/**
	 * Display the submission form with the captcha injected into it
	 */
	public function display_captcha( &$pfp_gateway_object, &$data, $error=array() ) {
		global $wgOut, $wgPayflowCaptcha;
		$form = $pfp_gateway_object->fnPayflowGenerateFormBody( $data, &$error );
		$form .= Xml::openElement( 'div', array( 'id' => 'mw-donate-captcha' ));

		// get the captcha
		$form .= $wgPayflowCaptcha->getForm();
		$form .= '<span class="creditcard-error-msg">' . wfMsg( 'payflowpro_gateway-error-msg-captcha-please') . '</span>';
		$form .= Xml::closeElement( 'div' );
		$form .= $pfp_gateway_object->fnPayflowGenerateFormSubmit( $data, &$error );
		$wgOut->addHTML( $form );
	}

	/**
	 * Wrapper for validating the captcha submission
	 */
	public function validate_captcha() {
		global $wgPayflowCaptcha;
		return $wgPayflowCaptcha->passCaptcha();
	}
}
